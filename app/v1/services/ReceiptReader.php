<?php
// app/v1/services/ReceiptReader.php
// Reads the amount spent from a receipt, when it can. Works for PDF receipts that contain text (e-receipts,
// M-Pesa statements, shop PDFs). Photos of paper receipts have no text to read without an OCR service, so for
// them it returns null and the admin types the amount. Never guesses: no clear total = null.

class ReceiptReader
{
    /** Amount on the receipt file, or null when it cannot be read. */
    public static function amountFromFile(string $path, string $mime): ?float
    {
        if ($mime !== 'application/pdf' || !is_file($path) || filesize($path) > 10 * 1024 * 1024) return null;
        return self::amountFromText(self::pdfText((string) file_get_contents($path)));
    }

    /** Plain text from a PDF: text drawn with Tj / TJ inside (optionally Flate-compressed) content streams. */
    public static function pdfText(string $pdf): string
    {
        $text = '';
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $m)) {
            foreach ($m[1] as $raw) {
                $data = @gzuncompress($raw);
                if ($data === false) $data = @gzinflate($raw);
                if ($data === false) $data = $raw;
                if (preg_match_all('/\[(.*?)\]\s*TJ|\((.*?)(?<!\\\\)\)\s*Tj|(T\*|Td|TD|ET)/s', $data, $ops, PREG_SET_ORDER)) {
                    foreach ($ops as $op) {
                        if (!empty($op[3])) { $text .= "\n"; continue; }
                        if (!empty($op[1])) {
                            preg_match_all('/\((.*?)(?<!\\\\)\)/s', $op[1], $parts);
                            $text .= implode('', $parts[1]);
                        } else {
                            $text .= $op[2] ?? '';
                        }
                    }
                }
            }
        }
        return stripcslashes($text);
    }

    /**
     * The total on a receipt's text. Looks for a line with "total" / "amount paid" / "amount" (in that order of trust),
     * and takes the money on that line. M-Pesa messages ("Ksh1,250.00 paid to …") are read too.
     */
    public static function amountFromText(string $text): ?float
    {
        $text = str_replace("\r", "\n", $text);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), 'strlen'));
        $money = '(?:KES|KSH|Ksh|Kshs\.?|KSh)?\s*([0-9]{1,3}(?:,[0-9]{3})+(?:\.[0-9]{1,2})?|[0-9]+(?:\.[0-9]{1,2})?)';
        foreach (['/\b(grand\s*total|total\s*paid|amount\s*paid|total\s*amount|total\s*due|total)\b/i', '/\bamount\b/i'] as $label) {
            $found = [];
            foreach ($lines as $i => $line) {
                if (!preg_match($label, $line) || preg_match('/sub\s*-?\s*total|total\s*items|total\s*qty/i', $line)) continue;
                // The amount is on the same line, or on the next line when the receipt puts it in a column.
                foreach ([$line, $lines[$i + 1] ?? ''] as $cand) {
                    if (preg_match_all('/' . $money . '/i', preg_replace($label, '', $cand), $mm)) {
                        $vals = array_map(fn($v) => (float) str_replace(',', '', $v), $mm[1]);
                        $vals = array_filter($vals, fn($v) => $v > 0 && $v < 100000000);
                        if ($vals) { $found[] = max($vals); break; }
                    }
                }
            }
            if ($found) return round(end($found), 2);   // the last total line is the final one (after tax / discounts)
        }
        if (preg_match('/(?:Ksh|KES)\s*([0-9][0-9,]*(?:\.[0-9]{1,2})?)\s*(?:paid|sent)\s+to/i', $text, $m)) {
            return round((float) str_replace(',', '', $m[1]), 2);
        }
        return null;
    }
}
