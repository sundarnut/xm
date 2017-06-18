<?php
// Module Name:  xm website
// Project:      xm website to track everyday expenses
//
// ipfunctions.php - functions to manipulate IPv4 and v6 addresses
//
// Functions:
// 1. inet_ptod - convert IP Address from presentation (a.b.c.d) to decimal
// 2. inet_dtop - convert IP Address from decimal to presentation
// 
// Cookies:
// None
//
// Session Variables:
// None
//
// Revisions:
//    1. Sundar Krishnamurthy - sundar_k@hotmail.com               06/17/2017      Coding and commenting started


/* Function 1 - Convert an IP address from presentation to decimal(39,0) format suitable for storage in MySQL
 * @param string $ip_address An IP address in IPv4, IPv6 or decimal notation
 * @return string The IP address in decimal notation
 */
function inet_ptod($ip_address) {

    // IPv4 address
    if (strpos($ip_address, ':') === false && strpos($ip_address, '.') !== false) {
        $ip_address = '::' . $ip_address;
    }   //  End if (strpos($ip_address, ':') === false && strpos($ip_address, '.') !== false)

    // IPv6 address
    if (strpos($ip_address, ':') !== false) {
        $network = inet_pton($ip_address);
        $parts = unpack('N*', $network);

        foreach ($parts as &$part) {
            if ($part < 0) {
                $part = bcadd((string) $part, '4294967296');
            }   //  End if ($part < 0)

            if (!is_string($part)) {
                $part = (string) $part;
            }   //  End if (!is_string($part))
        }   //  End foreach ($parts as &$part)

        $decimal = $parts[4];
        $decimal = bcadd($decimal, bcmul($parts[3], '4294967296'));
        $decimal = bcadd($decimal, bcmul($parts[2], '18446744073709551616'));
        $decimal = bcadd($decimal, bcmul($parts[1], '79228162514264337593543950336'));

        return $decimal;
    }   //  End if (strpos($ip_address, ':') !== false)

    // Decimal address
    return $ip_address;
}   //  End function inet_ptod($ip_address)


/* Function 2 - Convert an IP address from decimal(39,0) to presentation (IPv4 or v6)
 * @param string The IP address in decimal notation
 * @return string $ip_address An IP address in IPv4, IPv6 or decimal notation
 */
function inet_dtop($decimal) {
    // IPv4 or IPv6 format
    if (strpos($decimal, ':') !== false || strpos($decimal, '.') !== false) {
        return $decimal;
    }   //  End if (strpos($decimal, ':') !== false || strpos($decimal, '.') !== false)

    // Decimal format
    $parts = array();
    $parts[1] = bcdiv($decimal, '79228162514264337593543950336', 0);
    $decimal = bcsub($decimal, bcmul($parts[1], '79228162514264337593543950336'));
    $parts[2] = bcdiv($decimal, '18446744073709551616', 0);
    $decimal = bcsub($decimal, bcmul($parts[2], '18446744073709551616'));
    $parts[3] = bcdiv($decimal, '4294967296', 0);
    $decimal = bcsub($decimal, bcmul($parts[3], '4294967296'));
    $parts[4] = $decimal;

    foreach ($parts as &$part) {
        if (bccomp($part, '2147483647') == 1) {
            $part = bcsub($part, '4294967296');
        }   //  End if (bccomp($part, '2147483647') == 1)

        $part = (int) $part;
    }   //  End foreach ($parts as &$part)

    $network = pack('N4', $parts[1], $parts[2], $parts[3], $parts[4]);
    $ip_address = inet_ntop($network);

    // Turn IPv6 to IPv4 if it's IPv4
    if (preg_match('/^::\d+.\d+.\d+.\d+$/', $ip_address)) {
        return substr($ip_address, 2);
    }   //  End if (preg_match('/^::\d+.\d+.\d+.\d+$/', $ip_address))

    return $ip_address;
}   //  End function inet_dtop($decimal)
?>
