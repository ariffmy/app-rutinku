param(
    [string] $OutputDirectory = (Join-Path $PSScriptRoot '..\public\assets\icons')
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$output = [System.IO.Path]::GetFullPath($OutputDirectory)
[System.IO.Directory]::CreateDirectory($output) | Out-Null

function New-RutinKuIcon {
    param(
        [int] $Size,
        [string] $FileName
    )

    $bitmap = [System.Drawing.Bitmap]::new($Size, $Size)
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $graphics.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit
    $graphics.Clear([System.Drawing.ColorTranslator]::FromHtml('#345995'))

    $circleBrush = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::FromArgb(30, 255, 255, 255))
    $margin = [int] ($Size * 0.165)
    $graphics.FillEllipse($circleBrush, $margin, $margin, $Size - (2 * $margin), $Size - (2 * $margin))

    $font = [System.Drawing.Font]::new('Arial', $Size * 0.47, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Pixel)
    $textBrush = [System.Drawing.SolidBrush]::new([System.Drawing.Color]::White)
    $format = [System.Drawing.StringFormat]::new()
    $format.Alignment = [System.Drawing.StringAlignment]::Center
    $format.LineAlignment = [System.Drawing.StringAlignment]::Center
    $textRectangle = [System.Drawing.RectangleF]::new(0, -($Size * 0.025), $Size, $Size)
    $graphics.DrawString('R', $font, $textBrush, $textRectangle, $format)

    $checkPen = [System.Drawing.Pen]::new([System.Drawing.ColorTranslator]::FromHtml('#8fe3b0'), $Size * 0.065)
    $checkPen.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
    $checkPen.EndCap = [System.Drawing.Drawing2D.LineCap]::Round
    $checkPen.LineJoin = [System.Drawing.Drawing2D.LineJoin]::Round
    $points = [System.Drawing.PointF[]] @(
        [System.Drawing.PointF]::new($Size * 0.50, $Size * 0.68),
        [System.Drawing.PointF]::new($Size * 0.60, $Size * 0.78),
        [System.Drawing.PointF]::new($Size * 0.79, $Size * 0.56)
    )
    $graphics.DrawLines($checkPen, $points)

    $bitmap.Save((Join-Path $output $FileName), [System.Drawing.Imaging.ImageFormat]::Png)

    $checkPen.Dispose()
    $format.Dispose()
    $textBrush.Dispose()
    $font.Dispose()
    $circleBrush.Dispose()
    $graphics.Dispose()
    $bitmap.Dispose()
}

New-RutinKuIcon -Size 192 -FileName 'icon-192.png'
New-RutinKuIcon -Size 512 -FileName 'icon-512.png'
New-RutinKuIcon -Size 180 -FileName 'apple-touch-icon.png'

Write-Output "Generated RutinKu PWA icons in $output"
