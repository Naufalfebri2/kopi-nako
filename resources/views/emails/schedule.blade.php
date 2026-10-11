<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Jadwal Shift</title>
</head>

<body style="margin:0;padding:24px;background:#f7f5f2;font-family:Arial,Helvetica,sans-serif;color:#1a1714;">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;padding:28px;">
        <div style="font-size:18px;font-weight:bold;color:#b4763a;margin-bottom:16px;">Kopi Nako Pamulang</div>
        <p style="font-size:15px;line-height:1.6;margin:0 0 12px;">Halo {{ $name }},</p>
        <p style="font-size:15px;line-height:1.6;margin:0 0 20px;">
            Jadwal shift Anda untuk <strong>{{ $periodLabel }}</strong> sudah terbit. Jadwal lengkapnya ada pada
            lampiran PDF.
        </p>
        <a href="{{ url('/jadwal') }}"
            style="display:inline-block;background:#1a1714;color:#ffffff;text-decoration:none;padding:10px 18px;border-radius:999px;font-size:14px;">
            Buka Jadwal Shift
        </a>
        <p style="font-size:12px;color:#8a8279;margin:24px 0 0;">
            Email ini dikirim otomatis oleh Sistem Manajemen Shift Karyawan. Mohon tidak membalas email ini.
        </p>
    </div>
</body>

</html>
