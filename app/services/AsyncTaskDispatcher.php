<?php

class AsyncTaskDispatcher
{
    /** @var callable[] */
    private static $tasks = [];

    /** @var bool */
    private static $registered = false;

    public static function queue(callable $task): void
    {
        self::$tasks[] = $task;

        if (!self::$registered) {
            register_shutdown_function([self::class, 'run']);
            self::$registered = true;
        }
    }

    public static function run(): void
    {
        if (empty(self::$tasks)) {
            return;
        }

        @ignore_user_abort(true);
        @set_time_limit(0);

        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        } else {
            if (php_sapi_name() !== 'cli') {
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                @flush();
            }
        }

        foreach (self::$tasks as $task) {
            try {
                $task();
            } catch (\Throwable $exception) {
                error_log('Erro ao executar tarefa assíncrona: ' . $exception->getMessage());
            }
        }

        self::$tasks = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }
    }
}
