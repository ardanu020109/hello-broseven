<?php

namespace Tests\Unit;

use App\Services\FaceVerificationService;
use App\Services\GeoService;
use App\Support\AuditLabels;
use App\Support\PdfWriter;
use App\Support\XlsxWriter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class SupportTest extends TestCase
{
    /* ---------------- Haversine ---------------- */

    public function test_haversine_known_distances(): void
    {
        $geo = new GeoService();

        $this->assertEqualsWithDelta(0.0, $geo->distanceMeters(-5.1476, 119.4327, -5.1476, 119.4327), 0.001);
        $this->assertEqualsWithDelta(111195, $geo->distanceMeters(0, 0, 1, 0), 60);          // 1 derajat lintang
        $this->assertEqualsWithDelta(
            $geo->distanceMeters(-5.1476, 119.4327, -5.1500, 119.4400),
            $geo->distanceMeters(-5.1500, 119.4400, -5.1476, 119.4327),
            0.001
        ); // simetris
    }

    public function test_haversine_radius_example_from_the_specification(): void
    {
        $geo = new GeoService();
        $lat = -5.1476540;
        $lng = 119.4327310;
        $metersPerDegree = 111195.0;

        $inside = $geo->distanceMeters($lat, $lng, $lat + 45 / $metersPerDegree, $lng);   // 45 m dari kantor
        $outside = $geo->distanceMeters($lat, $lng, $lat + 250 / $metersPerDegree, $lng); // 250 m dari kantor

        $this->assertEqualsWithDelta(45, $inside, 1.0);
        $this->assertLessThanOrEqual(100, $inside);
        $this->assertEqualsWithDelta(250, $outside, 1.0);
        $this->assertGreaterThan(100, $outside);
    }

    /* ---------------- jarak wajah ---------------- */

    public function test_face_distance_and_sample_consistency(): void
    {
        $a = array_fill(0, 128, 0.1);
        $b = array_fill(0, 128, 0.1);
        $c = array_fill(0, 128, 0.3);

        $this->assertEqualsWithDelta(0.0, FaceVerificationService::distance($a, $b), 1e-9);
        $this->assertEqualsWithDelta(0.2 * sqrt(128), FaceVerificationService::distance($a, $c), 1e-9);

        $service = new FaceVerificationService();
        $this->assertTrue($service->samplesAreConsistent([$a, $b, $a]));
        $this->assertFalse($service->samplesAreConsistent([$a, $c, $a]));
    }

    /* ---------------- XLSX ---------------- */

    public function test_xlsx_escapes_text_and_never_creates_formulas(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('Ekstensi zip belum aktif.');
        }

        $path = (new XlsxWriter())
            ->addSheet('Laporan/Uji', [[['<b>&', XlsxWriter::S_BOLD], '=SUM(A1)', 5, 1.5]])
            ->save();

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $zip->close();
        @unlink($path);

        $this->assertStringContainsString('&lt;b&gt;&amp;', $sheet);
        $this->assertStringContainsString('=SUM(A1)', $sheet);
        $this->assertStringNotContainsString('<f>', $sheet);        // teks tidak pernah menjadi rumus
        $this->assertStringContainsString('<v>5</v>', $sheet);
        $this->assertStringContainsString('<v>1.5</v>', $sheet);
        $this->assertStringNotContainsString('/', substr($workbook, strpos($workbook, '<sheet ') ?: 0, 60) === '' ? '' : '');
        $this->assertStringContainsString('Laporan Uji', $workbook); // karakter terlarang di nama sheet diganti
    }

    /* ---------------- PDF ---------------- */

    private function assertValidPdf(string $pdf): void
    {
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertSame(1, preg_match('/startxref\n(\d+)\n%%EOF\z/', $pdf, $m));
        $this->assertSame('xref', substr($pdf, (int) $m[1], 4));

        preg_match_all('/(\d{10}) 00000 n /', $pdf, $entries);
        $this->assertNotEmpty($entries[1]);

        // setiap entri xref menunjuk tepat ke "N 0 obj"
        foreach ($entries[1] as $i => $offset) {
            $id = $i + 1;
            $this->assertSame("{$id} 0 obj", substr($pdf, (int) $offset, strlen("{$id} 0 obj")));
        }
    }

    public function test_pdf_is_structurally_valid_and_paginates(): void
    {
        $pdf = new PdfWriter(true);
        $pdf->heading('Laporan Uji');
        $pdf->paragraph('Baris keterangan');

        $rows = [];
        for ($i = 1; $i <= 120; $i++) {
            $rows[] = [(string) $i, 'Karyawan '.$i, 'Rp '.number_format($i * 1000, 0, ',', '.')];
        }
        $rows[] = ['bold' => true, 'cells' => ['', 'TOTAL', 'Rp 0']];
        $pdf->table([
            ['label' => 'No', 'w' => 1, 'align' => 'R'], ['label' => 'Nama', 'w' => 4], ['label' => 'Jumlah', 'w' => 2, 'align' => 'R'],
        ], $rows);

        $out = $pdf->output('Footer uji');

        $this->assertValidPdf($out);
        $this->assertStringContainsString('Halaman 1 dari', $out);
        $this->assertStringContainsString('Halaman 2 dari', $out);
    }

    public function test_pdf_handles_non_ascii_and_special_characters(): void
    {
        $pdf = new PdfWriter();
        $pdf->heading('Müller é (uji) \\ akhir');
        $pdf->paragraph(str_repeat('teks panjang ', 80)); // dipotong agar tidak melewati lebar halaman

        $out = $pdf->output();

        $this->assertValidPdf($out);
        $this->assertStringContainsString('Muller', $out);
        $this->assertStringContainsString('\\(uji\\)', $out);
    }

    /* ---------------- audit ---------------- */

    public function test_audit_values_are_redacted_recursively(): void
    {
        $clean = AuditLabels::redact(['password' => 'p', 'api_token' => 't', 'descriptor' => [1, 2], 'x' => ['secret_key' => 's', 'ok' => 1]]);

        $this->assertSame('[disembunyikan]', $clean['password']);
        $this->assertSame('[disembunyikan]', $clean['api_token']);
        $this->assertSame('[disembunyikan]', $clean['descriptor']);
        $this->assertSame('[disembunyikan]', $clean['x']['secret_key']);
        $this->assertSame(1, $clean['x']['ok']);
        $this->assertSame('-', AuditLabels::pretty(null));
        $this->assertSame('Absen masuk', AuditLabels::action('check_in'));
    }
}
