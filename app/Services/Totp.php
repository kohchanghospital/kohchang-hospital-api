<?php

namespace App\Services;

class Totp
{
    public function secret(): string
    {
        return $this->encodeBase32(random_bytes(20));
    }

    private function encodeBase32(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bytes) as $byte) $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        $encoded = '';
        foreach (str_split($bits, 5) as $group) $encoded .= $alphabet[bindec($group)];
        return $encoded;
    }

    public function uri(string $secret, string $email): string
    {
        $issuer = 'Kohchang Hospital';
        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($email)
            .'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    public function matchedCounter(string $secret, string $code, ?int $lastUsed = null): ?int
    {
        if (!preg_match('/^\d{6}$/D', $code)) return null;
        $now = intdiv(time(), 30);
        foreach ([$now - 1, $now, $now + 1] as $counter) {
            if ($counter > ($lastUsed ?? -1) && hash_equals($this->code($secret, $counter), $code)) {
                return $counter;
            }
        }
        return null;
    }

    private function code(string $secret, int $counter): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = 0;
        $value = 0;
        $key = '';
        foreach (str_split($secret) as $char) {
            $value = ($value << 5) | strpos($alphabet, $char);
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $key .= chr(($value >> $bits) & 255);
            }
        }
        $message = pack('N2', intdiv($counter, 4294967296), $counter % 4294967296);
        $hash = hash_hmac('sha1', $message, $key, true);
        $offset = ord($hash[19]) & 15;
        $number = ((ord($hash[$offset]) & 127) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);
        return str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
