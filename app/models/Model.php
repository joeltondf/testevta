<?php

declare(strict_types=1);

/**
 * Base model providing shared database utilities for concrete models.
 */
class Model
{
    /**
     * Active PDO connection instance.
     *
     * @var PDO
     */
    protected $pdo;

    /**
     * Base constructor storing the PDO instance for child classes.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Executes a prepared statement using the provided parameters.
     *
     * @param string $sql    SQL query to prepare.
     * @param array  $params Parameters to bind into the statement.
     *
     * @return PDOStatement Prepared statement after execution.
     *
     * @throws PDOException When the database operation fails.
     */
    protected function execute(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        if (!$statement->execute($params)) {
            throw new PDOException('Database statement execution failed.');
        }

        return $statement;
    }
}
