<?php

namespace LaraDumps\LaraDumpsCore\Support\WpQuery;

class QueryExecuted
{
    /**
     * The SQL query that was executed.
     *
     * @var string
     */
    public $sql;



    /**
     * Create a new event instance.
     *
     * @param  string  $sql
     */
    public function __construct(string $sql)
    {
        $this->sql = $sql;
    }

    /**
     * Get the raw SQL representation of the query with embedded bindings.
     *
     * @return string
     */
    public function toRawSql(): string
    {
        return $this->sql;
    }
}
