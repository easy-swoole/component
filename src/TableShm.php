<?php

declare(strict_types=1);

namespace EasySwoole\Component;

use InvalidArgumentException;
use LengthException;
use RuntimeException;
use Swoole\Table;
use UnexpectedValueException;

/**
 * 基于 Swoole Table 的跨进程结构化数据存储。
 *
 * Table 必须在创建子进程或启动 Swoole Server 之前完成初始化，之后派生的进程
 * 才能访问同一块共享内存。本类只负责值的序列化，不提供跨机器共享能力。
 */
class TableShm
{
    use Singleton;

    public const SERIALIZER_PHP = 0;
    public const SERIALIZER_JSON = 1;
    public const SERIALIZER_MSGPACK = 2;

    protected Table $table;

    protected int $serializer;

    protected int $columnSize;

    public function __construct(
        int $maxRows = 64,
        int $serializer = self::SERIALIZER_PHP,
        int $columnSize = 65535
    )
    {
        if ($maxRows <= 0) {
            throw new InvalidArgumentException('Max rows must be greater than zero.');
        }

        if ($columnSize <= 0) {
            throw new InvalidArgumentException('Column size must be greater than zero.');
        }

        $this->columnSize = $columnSize;

        if (!in_array($serializer, $this->supportedSerializers(), true)) {
            throw new InvalidArgumentException('Unsupported serializer.');
        }

        if ($serializer === self::SERIALIZER_MSGPACK && !$this->msgpackAvailable()) {
            throw new RuntimeException('The msgpack extension is required.');
        }

        $this->serializer = $serializer;
        $this->table = new Table($maxRows);
        $this->table->column('data', Table::TYPE_STRING, $this->columnSize);

        if (!$this->table->create()) {
            throw new RuntimeException('Failed to create Swoole Table.');
        }
    }

    /**
     * 获取并反序列化数据；键不存在时返回 null。
     */
    public function get(string $key): mixed
    {
        $data = $this->table->get($key, 'data');
        if ($data === false) {
            return null;
        }

        return $this->decode($data);
    }

    /**
     * 序列化后写入共享内存。
     *
     * Swoole Table 会静默截断过长字符串，因此写入前必须主动检查长度。
     */
    public function set(string $key, mixed $value): bool
    {
        $data = $this->encode($value);
        if (strlen($data) > $this->columnSize) {
            throw new LengthException('Serialized data exceeds the table column size.');
        }

        return $this->table->set($key, ['data' => $data]);
    }

    public function exists(string $key): bool
    {
        return $this->table->exists($key);
    }

    public function delete(string $key): bool
    {
        return $this->table->del($key);
    }

    /**
     * 清空全部数据，但保留共享内存表本身供后续使用。
     */
    public function clear(): void
    {
        foreach ($this->table as $key => $_row) {
            $this->table->del((string) $key);
        }
    }

    private function encode(mixed $value): string
    {
        return match ($this->serializer) {
            self::SERIALIZER_PHP => serialize($value),
            self::SERIALIZER_JSON => json_encode($value, JSON_THROW_ON_ERROR),
            self::SERIALIZER_MSGPACK => msgpack_pack($value),
        };
    }

    private function decode(string $data): mixed
    {
        return match ($this->serializer) {
            self::SERIALIZER_PHP => $this->phpUnserialize($data),
            self::SERIALIZER_JSON => json_decode($data, true, 512, JSON_THROW_ON_ERROR),
            self::SERIALIZER_MSGPACK => msgpack_unpack($data),
        };
    }

    private function phpUnserialize(string $data): mixed
    {
        $value = @unserialize($data);
        if ($value === false && $data !== serialize(false)) {
            throw new UnexpectedValueException('Invalid serialized data.');
        }

        return $value;
    }

    /**
     * @return list<int>
     */
    private function supportedSerializers(): array
    {
        return [
            self::SERIALIZER_PHP,
            self::SERIALIZER_JSON,
            self::SERIALIZER_MSGPACK,
        ];
    }

    private function msgpackAvailable(): bool
    {
        return function_exists('msgpack_pack') && function_exists('msgpack_unpack');
    }
}
