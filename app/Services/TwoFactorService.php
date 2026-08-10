<?php

namespace App\Services;

class TwoFactorService
{
    private array $base32Lookup = [
        'A' => 0,  'B' => 1,  'C' => 2,  'D' => 3,
        'E' => 4,  'F' => 5,  'G' => 6,  'H' => 7,
        'I' => 8,  'J' => 9,  'K' => 10, 'L' => 11,
        'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15,
        'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19,
        'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23,
        'Y' => 24, 'Z' => 25, '2' => 26, '3' => 27,
        '4' => 28, '5' => 29, '6' => 30, '7' => 31
    ];

    private array $base32Chars = [
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
        'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
        'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
        'Y', 'Z', '2', '3', '4', '5', '6', '7'
    ];

    public function generateSecret(int $length = 16): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $this->base32Chars[random_int(0, 31)];
        }
        return $secret;
    }

    public function getQRCodeUrl(string $companyName, string $companyEmail, string $secret): string
    {
        $companyName = urlencode($companyName);
        $companyEmail = urlencode($companyEmail);
        $otpauth = "otpauth://totp/{$companyName}:{$companyEmail}?secret={$secret}&issuer={$companyName}";
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($otpauth);
    }

    public function verifyKey(string $secret, string $key, int $window = 1): bool
    {
        $timeStamp = floor(time() / 30);
        
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->oathHotp($secret, $timeStamp + $i), $key)) {
                return true;
            }
        }
        return false;
    }

    private function oathHotp(string $secret, int $counter): string
    {
        $decodedSecret = $this->base32Decode($secret);
        
        // Pack counter into a 64-bit string
        $packedCounter = pack('J', $counter);

        // Generate HMAC-SHA1
        $hash = hash_hmac('sha1', $packedCounter, $decodedSecret, true);

        // Dynamic truncation
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncatedHash = substr($hash, $offset, 4);

        $value = unpack('N', $truncatedHash)[1];
        $value = $value & 0x7FFFFFFF;
        $modulo = pow(10, 6);

        return str_pad($value % $modulo, 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        if (empty($secret)) {
            return '';
        }

        $secret = strtoupper($secret);
        $secret = str_replace('=', '', $secret);
        $binaryString = '';

        foreach (str_split($secret) as $char) {
            if (!isset($this->base32Lookup[$char])) {
                continue; // Ignore invalid characters
            }
            $binaryString .= str_pad(decbin($this->base32Lookup[$char]), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($binaryString, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }
}
