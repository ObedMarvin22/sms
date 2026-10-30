<?php

namespace App\Support\Sms;

/**
 * How many SMS units (segments) a message uses. GSM-7 text: 160 characters in one, 153 per part
 * when longer; characters from the GSM extension table (€ ^ { } [ ] ~ \ |) count twice. Anything
 * else (e.g. "₵", emoji, accented letters outside GSM) switches the whole message to UCS-2:
 * 70 in one, 67 per part.
 */
final class SmsSegments
{
    private const BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    private const EXTENSION = "^{}\\[~]|€\f";

    public static function count(string $message): int
    {
        $length = 0;
        foreach (mb_str_split($message) as $char) {
            if (mb_strpos(self::BASIC, $char) !== false) {
                $length++;
            } elseif (mb_strpos(self::EXTENSION, $char) !== false) {
                $length += 2;
            } else {
                $units = mb_strlen($message);

                return $units <= 70 ? 1 : (int) ceil($units / 67);
            }
        }

        if ($length === 0) {
            return 0;
        }

        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }
}
