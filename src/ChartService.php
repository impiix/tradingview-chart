<?php

namespace TradingView;

use TradingView\Document\Image;
use TradingView\Service\UploadUrlGeneratorInterface;
use TradingView\Service\ImageManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ChartService {
    /**
     * @var ImageManagerInterface
     */
    protected $imageManager;

    /**
     * @var UploadUrlGeneratorInterface
     */
    protected $uploadUrlGenerator;

    /**
     * @var string
     */
    protected $fontPath;

    /**
     * @param ImageManagerInterface       $imageManager
     * @param UploadUrlGeneratorInterface $uploadUrlGenerator
     */
    public function __construct(
        ImageManagerInterface $imageManager,
        UploadUrlGeneratorInterface $uploadUrlGenerator,
        $fontPath
    ) {
        $this->imageManager = $imageManager;
        $this->uploadUrlGenerator = $uploadUrlGenerator;
        $this->fontPath = $fontPath;
    }

    /**
     * @param array $baseData
     *
     * @return string
     */
    public function save(array $baseData) {

        $height = 0;
        $width = 0;
        $container = [];

        $hidpi = $baseData['hidpiRatio'];
        $data = $baseData['charts'][0];

        $fontsize = 10 * $hidpi;
        $font = $this->fontPath;

        $color = $data['colors']['text'];
        $format = strlen($color) == 4 ? "#%1x%1x%1x" : "#%2x%2x%2x";
        list($r, $g, $b) = sscanf($color, $format);

        foreach($data['panes'] as $pane) {
            $contents = [
                $pane['content'],
                $pane['rightAxis']['content']
            ];
            $contents = array_map(
                function($el){
                    return imagecreatefromstring(base64_decode(substr($el, 22)));
                },
                $contents
            );

            //add labels if exist
            if(isset($pane['studies'])) {
                foreach($pane['studies'] as $index => $study) {
                    $color = imagecolorallocate($contents[0], $r, $g, $b);
                    //max 6 labels in column
                    $x = $fontsize + (intval($index/6) * imagesx($contents[0])/4);
                    $y = ($index%6) * 1.5 * $fontsize + 2 * $fontsize;

                    imagettftext($contents[0], $fontsize, 0, $x, $y, $color, $font, $study);
                }
            }

            $height += imagesy($contents[0]);
            $tmpWidth = imagesx($contents[0]) + imagesx($contents[1]);
            $tmpWidth > $width ? $width = $tmpWidth : null;

            $container[] = $contents;

        }

        //$data['timeAxis'][0]['rhsStub']['content'] notneeded
        $timeContent = imagecreatefromstring(base64_decode(substr($data['timeAxis']['content'], 22)));
        $height += imagesy($timeContent);

        $currentHeight = $fontsize * 3;
        $baseImage = imagecreatetruecolor($width/$hidpi, $height/$hidpi + $currentHeight);
        $white = imagecolorallocate($baseImage, 255, 255, 255);
        imagefill($baseImage, 0, 0, $white);

        foreach($container as $contents) {
            imagecopy($baseImage, $contents[0], 0, $currentHeight, 0, 0, imagesx($contents[0]), imagesy($contents[0]));
            imagecopy(
                $baseImage,
                $contents[1],
                imagesx($contents[0])/$hidpi,
                $currentHeight,
                0,
                0,
                imagesx($contents[1]),
                imagesy($contents[1])
            );
            $currentHeight += imagesy($contents[0])/$hidpi;
        }
        imagecopy($baseImage, $timeContent, 0, $currentHeight, 0, 0, imagesx($timeContent), imagesy($timeContent));

        //upper label
        $color = imagecolorallocate($baseImage, $r, $g, $b);
        $args = array_merge($data['meta'], $data['ohlc']);
        $text = vsprintf("%s, %s, %s, %s O:%s H:%s L:%s C:%s", $args);
        imagettftext($baseImage, $fontsize, 0, $fontsize, $fontsize * 2, $color, $font, $text);

        //border
        $black = imagecolorallocate($baseImage, 0, 0, 0);
        imagerectangle($baseImage, 0, 0, imagesx($baseImage) - 1, imagesy($baseImage) -1, $black);

        //save
        $id = md5(rand(0, getrandmax()));
        $filename = '/tmp/'. $id . '.png';
        imagepng($baseImage, $filename);
        $file = new UploadedFile($filename, $id);
        $image = new Image();
        $image->setImage($file);
        $this->imageManager->saveImage($image);

        unlink($filename);

        $path = $this->uploadUrlGenerator->generateUrl($image->getPath());

        return $path;
    }
}
