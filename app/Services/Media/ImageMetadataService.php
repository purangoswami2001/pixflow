<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ImageMetadataService
{
    /** @var list<string> */
    protected array $stripChunkTypes = ['tEXt', 'iTXt', 'zTXt', 'eXIf', 'caBX', 'pHYs'];

    /** @var list<string> */
    protected array $aiKeywords = [
        'dall-e', 'dalle', 'openai', 'midjourney', 'stable diffusion', 'stability ai',
        'gemini', 'google ai', 'adobe firefly', 'firefly', 'generative', 'ai generated',
        'artificial intelligence', 'chatgpt', 'gpt-4', 'imagen', 'cursor', 'c2pa',
        'content credentials', 'prompt', 'negative prompt', 'diffusion',
    ];

    public function canvaStyleFileName(?int $variant = null): string
    {
        $base = pathinfo((string) config('media.canva.file_name', 'Untitled design.png'), PATHINFO_FILENAME);

        if ($variant === null || $variant <= 1) {
            return $base.'.png';
        }

        return "{$base} ({$variant}).png";
    }

    public function nextAvailableFileName(string $directory): string
    {
        $preferred = (string) config('media.canva.file_name', 'Untitled design.png');
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $directory = trim($directory, '/');

        if (! $disk->exists("{$directory}/{$preferred}")) {
            return $preferred;
        }

        $base = pathinfo($preferred, PATHINFO_FILENAME);
        $variant = 1;

        while ($disk->exists("{$directory}/{$base} ({$variant}).png")) {
            $variant++;
        }

        return "{$base} ({$variant}).png";
    }

    public function applyCanvaMetadata(string $absolutePath): void
    {
        if (! is_file($absolutePath)) {
            return;
        }

        $binary = file_get_contents($absolutePath);
        if ($binary === false || $binary === '') {
            return;
        }

        if ($this->isPng($binary)) {
            file_put_contents($absolutePath, $this->rebuildPngWithCanvaMetadata($binary));

            return;
        }

        if ($this->isJpeg($binary)) {
            $binary = $this->stripJpegAppSegments($binary);
            $binary = $this->injectJpegXmp($binary);

            file_put_contents($absolutePath, $binary);
        }
    }

    protected function isPng(string $binary): bool
    {
        return str_starts_with($binary, "\x89PNG\r\n\x1a\n");
    }

    protected function isJpeg(string $binary): bool
    {
        return str_starts_with($binary, "\xFF\xD8\xFF");
    }

    /**
     * @return list<array{type: string, data: string, raw: string}>
     */
    protected function parsePngChunks(string $png): array
    {
        $chunks = [];
        $offset = 8;
        $length = strlen($png);

        while ($offset + 12 <= $length) {
            $chunkLen = unpack('N', substr($png, $offset, 4))[1];
            if ($chunkLen > 50_000_000 || $offset + 12 + $chunkLen > $length) {
                break;
            }

            $type = substr($png, $offset + 4, 4);
            $data = substr($png, $offset + 8, $chunkLen);
            $chunks[] = [
                'type' => $type,
                'data' => $data,
                'raw' => substr($png, $offset, 12 + $chunkLen),
            ];

            $offset += 12 + $chunkLen;

            if ($type === 'IEND') {
                break;
            }
        }

        return $chunks;
    }

    protected function rebuildPngWithCanvaMetadata(string $png): string
    {
        $chunks = $this->parsePngChunks($png);
        if ($chunks === []) {
            return $png;
        }

        $profile = $this->canvaProfile();
        $metaChunks = $this->makePhysChunk()
            .$this->makeITxtChunk('XML:com.adobe.xmp', $this->canvaXmp($profile))
            .$this->makeExifChunk($profile['author'], $profile['title']);

        $out = substr($png, 0, 8);
        $idatChunks = [];
        $iendChunk = null;

        foreach ($chunks as $chunk) {
            if ($chunk['type'] === 'IHDR') {
                $out .= $chunk['raw'].$metaChunks;
            } elseif ($chunk['type'] === 'IDAT') {
                $idatChunks[] = $chunk['raw'];
            } elseif ($chunk['type'] === 'IEND') {
                $iendChunk = $chunk['raw'];
            } elseif (! in_array($chunk['type'], $this->stripChunkTypes, true)
                && $chunk['type'] !== 'IHDR') {
                $out .= $chunk['raw'];
            }
        }

        $out .= implode('', $idatChunks);

        return $out.($iendChunk ?? $this->makeIendChunk());
    }

    /**
     * @return array{
     *   author: string,
     *   title: string,
     *   doc: string,
     *   user: string,
     *   brand: string,
     *   ext_id: string,
     *   fb_id: string,
     *   touch_type: int,
     *   created_date: string,
     *   creator_tool: string
     * }
     */
    protected function canvaProfile(): array
    {
        $ids = Cache::rememberForever('PixFlow.canva_metadata_ids', function () {
            return [
                'doc' => $this->configuredOrGeneratedId('media.canva.doc_id'),
                'user' => $this->configuredOrGeneratedId('media.canva.user_id'),
                'brand' => $this->configuredOrGeneratedId('media.canva.brand_id'),
            ];
        });

        $author = trim((string) config('media.canva.author', 'Puran Goswami'));
        $title = trim((string) config('media.canva.title', 'Untitled design - 1'));
        $doc = $ids['doc'];
        $user = $ids['user'];
        $brand = $ids['brand'];
        $fbId = trim((string) config('media.canva.fb_id', ''));

        if ($fbId === '') {
            $fbId = (string) random_int(100000000000000, 999999999999999);
        }

        return [
            'author' => $author,
            'title' => $title,
            'doc' => $doc,
            'user' => $user,
            'brand' => $brand,
            'ext_id' => (string) Str::uuid(),
            'fb_id' => $fbId,
            'touch_type' => (int) config('media.canva.touch_type', 2),
            'created_date' => now()->format('Y-m-d'),
            'creator_tool' => "Canva (Renderer) doc={$doc} user={$user} brand={$brand}",
        ];
    }

    protected function configuredOrGeneratedId(string $configKey): string
    {
        $configured = trim((string) config($configKey, ''));

        return $configured !== '' ? $configured : $this->randomCanvaId();
    }

    protected function randomCanvaId(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $id = '';

        for ($i = 0; $i < 11; $i++) {
            $id .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $id;
    }

    /**
     * @param  array{
     *   author: string,
     *   title: string,
     *   doc: string,
     *   user: string,
     *   brand: string,
     *   ext_id: string,
     *   fb_id: string,
     *   touch_type: int,
     *   created_date: string,
     *   creator_tool: string
     * }  $profile
     */
    protected function canvaXmp(array $profile): string
    {
        $adsData = json_encode([
            'doc' => $profile['doc'],
            'user' => $profile['user'],
            'brand' => $profile['brand'],
        ], JSON_UNESCAPED_SLASHES);

        $title = htmlspecialchars($profile['title'], ENT_XML1);
        $author = htmlspecialchars($profile['author'], ENT_XML1);
        $creatorTool = htmlspecialchars($profile['creator_tool'], ENT_XML1);
        $created = htmlspecialchars($profile['created_date'], ENT_XML1);
        $extId = htmlspecialchars($profile['ext_id'], ENT_XML1);
        $fbId = htmlspecialchars($profile['fb_id'], ENT_XML1);
        $touchType = (int) $profile['touch_type'];
        $adsDataXml = htmlspecialchars($adsData, ENT_XML1);

        return <<<XMP
<?xpacket begin="\xEF\xBB\xBF" id="W5M0MpCehiHzreSzNTczkc9d"?>
<x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="Adobe XMP Core 5.6-c148 79.164024, 2019/08/13-01:08:57        ">
  <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
    <rdf:Description rdf:about=""
      xmlns:ads="http://ns.canva.com/ads/1.0/"
      xmlns:dc="http://purl.org/dc/elements/1.1/"
      xmlns:xmp="http://ns.adobe.com/xap/1.0/"
      xmlns:tiff="http://ns.adobe.com/tiff/1.0/"
      xmlns:exif="http://ns.adobe.com/exif/1.0/"
      xmlns:xml="http://www.w3.org/XML/1998/namespace">
      <ads:created>{$created}</ads:created>
      <ads:data>{$adsDataXml}</ads:data>
      <ads:ext_id>{$extId}</ads:ext_id>
      <ads:fb_id>{$fbId}</ads:fb_id>
      <ads:touch_type>{$touchType}</ads:touch_type>
      <dc:title>
        <rdf:Alt>
          <rdf:li xml:lang="x-default">{$title}</rdf:li>
        </rdf:Alt>
      </dc:title>
      <dc:creator>
        <rdf:Seq>
          <rdf:li>{$author}</rdf:li>
        </rdf:Seq>
      </dc:creator>
      <xmp:CreatorTool>{$creatorTool}</xmp:CreatorTool>
      <tiff:Orientation>1</tiff:Orientation>
      <tiff:XResolution>96/1</tiff:XResolution>
      <tiff:YResolution>96/1</tiff:YResolution>
      <tiff:ResolutionUnit>2</tiff:ResolutionUnit>
      <exif:ColorSpace>1</exif:ColorSpace>
      <exif:YCbCrPositioning>1</exif:YCbCrPositioning>
    </rdf:Description>
  </rdf:RDF>
</x:xmpmeta>
<?xpacket end="w"?>
XMP;
    }

    protected function makePhysChunk(): string
    {
        $data = pack('N', 3780).pack('N', 3780).chr(1);

        return pack('N', 9).'pHYs'.$data.$this->chunkCrc('pHYs', $data);
    }

    protected function makeExifChunk(string $author, string $title): string
    {
        $exif = $this->buildTiffExif($author, $title);

        return pack('N', strlen($exif)).'eXIf'.$exif.$this->chunkCrc('eXIf', $exif);
    }

    protected function buildTiffExif(string $artist, string $description): string
    {
        $description .= "\0";
        $artist .= "\0";

        if (strlen($description) % 2) {
            $description .= "\0";
        }
        if (strlen($artist) % 2) {
            $artist .= "\0";
        }

        $entryCount = 6;
        $ifdSize = 2 + ($entryCount * 12) + 4;
        $dataOffset = 8 + $ifdSize;

        $stringBlock = $description.$artist;
        $rationalOffset = $dataOffset + strlen($stringBlock);
        $descriptionOffset = $dataOffset;
        $artistOffset = $dataOffset + strlen($description);
        $xResolutionOffset = $rationalOffset;
        $yResolutionOffset = $rationalOffset + 8;

        $ifd = pack('n', $entryCount);
        $ifd .= $this->ifdEntry(0x010E, 2, strlen($description), $descriptionOffset);
        $ifd .= $this->ifdEntry(0x013B, 2, strlen($artist), $artistOffset);
        $ifd .= $this->ifdEntry(0x011A, 5, 1, $xResolutionOffset);
        $ifd .= $this->ifdEntry(0x011B, 5, 1, $yResolutionOffset);
        $ifd .= $this->ifdEntryShort(0x0128, 2);
        $ifd .= $this->ifdEntryShort(0x0213, 1);
        $ifd .= pack('N', 0);

        $rationals = pack('N', 96).pack('N', 1).pack('N', 96).pack('N', 1);

        return "MM\x00\x2A\x00\x00\x00\x08".$ifd.$stringBlock.$rationals;
    }

    protected function ifdEntry(int $tag, int $type, int $count, int $offset): string
    {
        return pack('n', $tag).pack('n', $type).pack('N', $count).pack('N', $offset);
    }

    protected function ifdEntryShort(int $tag, int $value): string
    {
        return pack('n', $tag).pack('n', 3).pack('N', 1).pack('n', $value)."\0\0";
    }

    protected function makeITxtChunk(string $keyword, string $text): string
    {
        $data = $keyword."\0\x00\x00".''."\0".''."\0".$text;

        return pack('N', strlen($data)).'iTXt'.$data.$this->chunkCrc('iTXt', $data);
    }

    protected function makeIendChunk(): string
    {
        return pack('N', 0).'IEND'.$this->chunkCrc('IEND', '');
    }

    protected function chunkCrc(string $type, string $data): string
    {
        return pack('N', crc32($type.$data) & 0xFFFFFFFF);
    }

    protected function stripJpegAppSegments(string $jpeg): string
    {
        $out = "\xFF\xD8";
        $offset = 2;
        $length = strlen($jpeg);

        while ($offset + 4 <= $length) {
            if ($jpeg[$offset] !== "\xFF") {
                break;
            }

            $marker = $jpeg[$offset + 1];
            if ($marker === "\xDA") {
                return $out.substr($jpeg, $offset);
            }

            $segmentLen = unpack('n', substr($jpeg, $offset + 2, 2))[1];
            $segmentTotal = 2 + $segmentLen;
            if ($offset + $segmentTotal > $length) {
                break;
            }

            $segment = substr($jpeg, $offset, $segmentTotal);
            $isApp = $marker >= "\xE0" && $marker <= "\xEF";
            $shouldStrip = $isApp && (
                str_starts_with($segment, "\xFF\xE1")
                || str_starts_with($segment, "\xFF\xEB")
                || $this->chunkLooksAiRelated($segment)
            );

            if (! $shouldStrip) {
                $out .= $segment;
            }

            $offset += $segmentTotal;
        }

        return $out;
    }

    protected function chunkLooksAiRelated(string $data): bool
    {
        $haystack = strtolower($data);

        foreach ($this->aiKeywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function injectJpegXmp(string $jpeg): string
    {
        $xmp = $this->canvaXmp($this->canvaProfile());
        $namespace = 'http://ns.adobe.com/xap/1.0/';
        $payload = 'XMP'.$namespace."\x00".$xmp;
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        if (strlen($jpeg) < 2 || ! str_starts_with($jpeg, "\xFF\xD8")) {
            return $jpeg;
        }

        return "\xFF\xD8".$segment.substr($jpeg, 2);
    }
}
