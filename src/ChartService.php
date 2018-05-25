<?php

namespace TradingView;

class ChartService {

    protected $config;

    public function __construct(
        array $config
    ) {
        $this->config = $config['parameters'];
    }

    public function save(string $baseData): string {

        $height = 0;
        $width = 0;
        $container = [];

        $baseData = json_decode($baseData, true);

        if (!isset($baseData['hidpiRatio']) || !isset($baseData['charts'][0])) {
            throw new \RuntimeException("Incorrect json data provided!");
        }

        $hidpi = $baseData['hidpiRatio'];
        $data = $baseData['charts'][0];

        $fontsize = 10 * $hidpi;
        $font = $this->config["chart_font_path"];

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
        $filename = sprintf("%s/%s.png", $this->config['output_path'], $id);
        imagepng($baseImage, $filename);

        return $_SERVER['SERVER_NAME'] . sprintf("%s/%s.png", $this->config['server_path'], $id);
    }
}
