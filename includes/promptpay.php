<?php
// PromptPay QR payload generator (EMVCo / ITMX standard)
// อ้างอิง: standard ของ ITMX สำหรับ PromptPay
// $target = เบอร์โทร (0XXXXXXXXX) | เลข ปชช. 13 หลัก | e-Wallet 15 หลัก
// $type   = 'phone' | 'nid' | 'ewallet'
// $amount = ตัวเลขเงิน (null = QR แบบ static ไม่ระบุยอด)

function promptpay_payload($target, $type, $amount = null) {
    $target = preg_replace('/\D+/', '', $target);
    if ($target === '') return '';

    if ($type === 'phone') {
        // normalize → เอาเฉพาะ 9 ตัวท้าย (subscriber number) แล้วเติม 0066
        $target = ltrim($target, '0');
        if (strlen($target) >= 11 && substr($target, 0, 2) === '66') {
            $target = substr($target, 2);
        }
        $accId = '0066' . str_pad($target, 9, '0', STR_PAD_LEFT);
        $accId = substr(str_pad($accId, 13, '0', STR_PAD_LEFT), -13);
        $subTag = '01';
    } elseif ($type === 'nid') {
        $accId = str_pad($target, 13, '0', STR_PAD_LEFT);
        $subTag = '02';
    } else { // ewallet
        $accId = str_pad($target, 15, '0', STR_PAD_LEFT);
        $subTag = '03';
    }

    $merchantAcct =
        _pp_tlv('00', 'A000000677010111') .
        _pp_tlv($subTag, $accId);

    $hasAmount = ($amount !== null && $amount !== '' && (float)$amount > 0);

    $payload =
        _pp_tlv('00', '01') .                                    // payload format
        _pp_tlv('01', $hasAmount ? '12' : '11') .                // dynamic/static
        _pp_tlv('29', $merchantAcct) .                           // merchant account info
        _pp_tlv('53', '764') .                                   // THB
        ($hasAmount ? _pp_tlv('54', number_format((float)$amount, 2, '.', '')) : '') .
        _pp_tlv('58', 'TH');                                     // country

    $payload .= '6304';                                          // CRC tag + length placeholder
    $crc = _pp_crc16($payload);
    return $payload . $crc;
}

function _pp_tlv($id, $value) {
    return $id . str_pad((string)strlen($value), 2, '0', STR_PAD_LEFT) . $value;
}

function _pp_crc16($data) {
    $crc = 0xFFFF;
    $len = strlen($data);
    for ($i = 0; $i < $len; $i++) {
        $crc ^= (ord($data[$i]) << 8);
        for ($j = 0; $j < 8; $j++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
            $crc &= 0xFFFF;
        }
    }
    return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
}
