<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use League\ColorExtractor\Color;
use League\ColorExtractor\ColorExtractor;
use League\ColorExtractor\Palette;
use Illuminate\Support\Facades\Validator;

class PictureAnalysisController extends Controller
{
    public function index()
    {
        return view('index');
    }

    public function createWetSeal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => ['required','mimes:png,jpg,webp','max:2048'],
        ]);

        if ($validator->fails()) {
            return back()->withInput()->withErrors($validator);
        }

        $ext = $request->image->extension();
        $pathImage = $this->uploadImage($request);
        $sealColor = $this->getSealColor($pathImage);
        $this->createWaterMark($pathImage, $sealColor, $ext);

        return back()->withInput()->with('successfully', 'Image uploaded successfully! "public/images"');
    }

    private function uploadImage($request): string
    {
        $imageName = time().'.'.$request->image->extension();
        $publicImages = public_path('images');
        $request->image->move($publicImages, $imageName);

        return $publicImages . '/' . $imageName;
    }

    private function getSealColor($pathImage): array
    {
        $palette = Palette::fromFilename($pathImage);
        $extractor = new ColorExtractor($palette);
        $colors = $extractor->extract();
        $mainColorImage = Color::fromIntToRgb($colors[0]);
        $hue = $this->rgb2hue($mainColorImage);

        if ($hue>320 || $hue<=40) {
            $sealColor = [0, 0, 0];
        } elseif ($hue>175 && $hue<=260) {
            $sealColor = [255, 0, 0];
        } elseif ($hue>70 && $hue<=175) {
            $sealColor = [255, 255, 0];
        } else {
            $sealColor = [255, 255, 255];
        }

        return $sealColor;
    }

    private function createWaterMark($pathImage, $sealColor, $extension)
    {
        if ($extension == 'png') {
            $image = imagecreatefrompng($pathImage);
            imagesavealpha($image, true);
        } else if ($extension == 'jpg') {
            $image = imagecreatefromjpeg($pathImage);
            imagesavealpha($image, true);
        } else if ($extension == 'webp') {
            $image = imagecreatefromwebp($pathImage);
            imagesavealpha($image, true);
        }

        $imageSize = getimagesize($pathImage);

        $width = $imageSize[0];
        $height = $imageSize[1];
        $x = ($width / 2) - 30;
        $y = ($height / 2) + 30;

        $sealMark = imagecolorallocate($image, $sealColor[0], $sealColor[1], $sealColor[2]);
        $font = public_path('font') . '/Roboto-Black.ttf';
        imagettftext($image, 50, 0, $x, $y, $sealMark, $font, " SayLab ");

        if ($extension == 'png') {
            imagepng($image, $pathImage);
        } elseif ($extension == 'jpg') {
            imagejpeg($image, $pathImage);
        } else {
            imagewebp($image, $pathImage);
        }
    }

    private function rgb2hue($RGB) {
        $r = $RGB['r']/255;
        $g = $RGB['g']/255;
        $b = $RGB['b']/255;
        $max = max( $r, $g, $b );
        $min = min( $r, $g, $b );

        $d = $max - $min;
        switch($max){
            case $r:
                $h = 60 * fmod( ( ( $g - $b ) / $d ), 6 );
                if ($b > $g) {
                    $h += 360;
                }
                break;
            case $g:
                $h = 60 * ( ( $b - $r ) / $d + 2 );
                break;
            case $b:
                $h = 60 * ( ( $r - $g ) / $d + 4 );
                break;
        }

        return round($h);
    }

}
