<?php

namespace Sgdg\Vendor;

use Sgdg\Vendor\ParagonIE\ConstantTime\Base32;
use Sgdg\Vendor\ParagonIE\ConstantTime\Base32Hex;
use Sgdg\Vendor\ParagonIE\ConstantTime\Base64;
use Sgdg\Vendor\ParagonIE\ConstantTime\Base64DotSlash;
use Sgdg\Vendor\ParagonIE\ConstantTime\Base64DotSlashOrdered;
use Sgdg\Vendor\ParagonIE\ConstantTime\Base64UrlSafe;
use Sgdg\Vendor\ParagonIE\ConstantTime\Encoding;
use Sgdg\Vendor\ParagonIE\ConstantTime\Hex;
class EncodingTest extends PHPUnit_Framework_TestCase
{
    public function testBase32Encode()
    {
        $this->assertSame(Encoding::base32Encode("\x00"), 'aa======');
        $this->assertSame(Encoding::base32Encode("\x00\x00"), 'aaaa====');
        $this->assertSame(Encoding::base32Encode("\x00\x00\x00"), 'aaaaa===');
        $this->assertSame(Encoding::base32Encode("\x00\x00\x00\x00"), 'aaaaaaa=');
        $this->assertSame(Encoding::base32Encode("\x00\x00\x00\x00\x00"), 'aaaaaaaa');
        $this->assertSame(Encoding::base32Encode("\x00\x00\x0f\xff\xff"), 'aaaa7777');
        $this->assertSame(Encoding::base32Encode("\xff\xff\xf0\x00\x00"), '7777aaaa');
        $this->assertSame(Encoding::base32Encode("\xces\x9c\xe79"), 'zzzzzzzz');
        $this->assertSame(Encoding::base32Encode("ֵ\xadkZ"), '22222222');
        $this->assertSame(Base32::encodeUpper("\x00"), 'AA======');
        $this->assertSame(Base32::encodeUpper("\x00\x00"), 'AAAA====');
        $this->assertSame(Base32::encodeUpper("\x00\x00\x00"), 'AAAAA===');
        $this->assertSame(Base32::encodeUpper("\x00\x00\x00\x00"), 'AAAAAAA=');
        $this->assertSame(Base32::encodeUpper("\x00\x00\x00\x00\x00"), 'AAAAAAAA');
        $this->assertSame(Base32::encodeUpper("\x00\x00\x0f\xff\xff"), 'AAAA7777');
        $this->assertSame(Base32::encodeUpper("\xff\xff\xf0\x00\x00"), '7777AAAA');
        $this->assertSame(Base32::encodeUpper("\xces\x9c\xe79"), 'ZZZZZZZZ');
        $this->assertSame(Base32::encodeUpper("ֵ\xadkZ"), '22222222');
    }
    public function testBase32Hex()
    {
        $this->assertSame(Base32Hex::encode("\x00"), '00======');
        $this->assertSame(Base32Hex::encode("\x00\x00"), '0000====');
        $this->assertSame(Base32Hex::encode("\x00\x00\x00"), '00000===');
        $this->assertSame(Base32Hex::encode("\x00\x00\x00\x00"), '0000000=');
        $this->assertSame(Base32Hex::encode("\x00\x00\x00\x00\x00"), '00000000');
        $this->assertSame(Base32Hex::encode("\x00\x00\x0f\xff\xff"), '0000vvvv');
        $this->assertSame(Base32Hex::encode("\xff\xff\xf0\x00\x00"), 'vvvv0000');
    }
    /**
     * Based on test vectors from RFC 4648
     */
    public function testBase32Decode()
    {
        $this->assertSame("\x00\x00\x00\x00\x00\x00", Encoding::base32Decode('aaaaaaaaaa======'));
        $this->assertSame("\x00\x00\x00\x00\x00\x00\x00", Encoding::base32Decode('aaaaaaaaaaaa===='));
        $this->assertSame("\x00\x00\x00\x00\x00\x00\x00\x00", Encoding::base32Decode('aaaaaaaaaaaaa==='));
        $this->assertSame("\x00\x00\x00\x00\x00\x00\x00\x00\x00", Encoding::base32Decode('aaaaaaaaaaaaaaa='));
        $this->assertSame("\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00", Encoding::base32Decode('aaaaaaaaaaaaaaaa'));
        $this->assertSame("\x00", Encoding::base32Decode('aa======'));
        $this->assertSame("\x00\x00", Encoding::base32Decode('aaaa===='));
        $this->assertSame("\x00\x00\x00", Encoding::base32Decode('aaaaa==='));
        $this->assertSame("\x00\x00\x00\x00", Encoding::base32Decode('aaaaaaa='));
        $this->assertSame("\x00\x00\x00\x00\x00", Encoding::base32Decode('aaaaaaaa'));
        $this->assertSame("\x00\x00\x0f\xff\xff", Encoding::base32Decode('aaaa7777'));
        $this->assertSame("\xff\xff\xf0\x00\x00", Encoding::base32Decode('7777aaaa'));
        $this->assertSame("\xces\x9c\xe79", Encoding::base32Decode('zzzzzzzz'));
        $this->assertSame("ֵ\xadkZ", Encoding::base32Decode('22222222'));
        $this->assertSame('foobar', Encoding::base32Decode('mzxw6ytboi======'));
        $rand = \random_bytes(9);
        $enc = Encoding::base32Encode($rand);
        $this->assertSame(Encoding::base32Encode($rand), Encoding::base32Encode(Encoding::base32Decode($enc)));
        $this->assertSame($rand, Encoding::base32Decode($enc));
    }
    /**
     * @covers Encoding::hexDecode()
     * @covers Encoding::hexEncode()
     * @covers Encoding::base32Decode()
     * @covers Encoding::base32Encode()
     * @covers Encoding::base64Decode()
     * @covers Encoding::base64Encode()
     * @covers Encoding::base64DotSlashDecode()
     * @covers Encoding::base64DotSlashEncode()
     * @covers Encoding::base64DotSlashOrderedDecode()
     * @covers Encoding::base64DotSlashOrderedEncode()
     */
    public function testBasicEncoding()
    {
        // Re-run the test at least 3 times for each length
        for ($j = 0; $j < 3; ++$j) {
            for ($i = 1; $i < 84; ++$i) {
                $rand = \random_bytes($i);
                $enc = Encoding::hexEncode($rand);
                $this->assertSame(\bin2hex($rand), $enc, "Hex Encoding - Length: " . $i);
                $this->assertSame($rand, Encoding::hexDecode($enc), "Hex Encoding - Length: " . $i);
                // Uppercase variant:
                $enc = Hex::encodeUpper($rand);
                $this->assertSame(\strtoupper(\bin2hex($rand)), $enc, "Hex Encoding - Length: " . $i);
                $this->assertSame($rand, Hex::decode($enc), "HexUpper Encoding - Length: " . $i);
                $enc = Encoding::base32Encode($rand);
                $this->assertSame($rand, Encoding::base32Decode($enc), "Base32 Encoding - Length: " . $i);
                $enc = Encoding::base32EncodeUpper($rand);
                $this->assertSame($rand, Encoding::base32DecodeUpper($enc), "Base32Upper Encoding - Length: " . $i);
                $enc = Encoding::base32HexEncode($rand);
                $this->assertSame(\bin2hex($rand), \bin2hex(Encoding::base32HexDecode($enc)), "Base32Hex Encoding - Length: " . $i);
                $enc = Encoding::base32HexEncodeUpper($rand);
                $this->assertSame(\bin2hex($rand), \bin2hex(Encoding::base32HexDecodeUpper($enc)), "Base32HexUpper Encoding - Length: " . $i);
                $enc = Encoding::base64Encode($rand);
                $this->assertSame($rand, Encoding::base64Decode($enc), "Base64 Encoding - Length: " . $i);
                $enc = Encoding::base64EncodeDotSlash($rand);
                $this->assertSame($rand, Encoding::base64DecodeDotSlash($enc), "Base64 DotSlash Encoding - Length: " . $i);
                $enc = Encoding::base64EncodeDotSlashOrdered($rand);
                $this->assertSame($rand, Encoding::base64DecodeDotSlashOrdered($enc), "Base64 Ordered DotSlash Encoding - Length: " . $i);
                $enc = Base64UrlSafe::encode($rand);
                $this->assertSame(\strtr(\base64_encode($rand), '+/', '-_'), $enc);
                $this->assertSame($rand, Base64UrlSafe::decode($enc));
            }
        }
    }
}
