<?php

declare(strict_types=1);

class DocumentValidator
{
    public static function sanitizeNumber(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    public static function isValidCpf(string $cpf): bool
    {
        $digits = self::sanitizeNumber($cpf);
        if (strlen($digits) !== 11) {
            return false;
        }

        if (preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($c = 0; $c < $t; $c++) {
                $sum += (int) $digits[$c] * (($t + 1) - $c);
            }
            $digit = (($sum * 10) % 11) % 10;
            if ((int) $digits[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    public static function isValidCnpj(string $cnpj): bool
    {
        $digits = self::sanitizeNumber($cnpj);
        if (strlen($digits) !== 14) {
            return false;
        }

        if (preg_match('/^(\d)\1{13}$/', $digits)) {
            return false;
        }

        $length = 12;
        $numbers = substr($digits, 0, $length);
        $validators = substr($digits, $length);

        $weights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < $length; $i++) {
            $sum += (int) $numbers[$i] * $weights[$i];
        }
        $remainder = $sum % 11;
        $firstDigit = $remainder < 2 ? 0 : 11 - $remainder;
        if ((int) $validators[0] !== $firstDigit) {
            return false;
        }

        $length = 13;
        $numbers = substr($digits, 0, $length);
        $weights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < $length; $i++) {
            $sum += (int) $numbers[$i] * $weights[$i];
        }
        $remainder = $sum % 11;
        $secondDigit = $remainder < 2 ? 0 : 11 - $remainder;
        if ((int) $validators[1] !== $secondDigit) {
            return false;
        }

        return true;
    }
}
