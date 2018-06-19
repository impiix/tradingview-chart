<?php

namespace TradingView;

class ChartService
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var int
     */
    protected $height;

    /**
     * @var int
     */
    protected $width;

    /**
     * @var string
     */
    protected $font;
    
    public function __construct(
        array $config
    ) {
        $this->config = $config['parameters'];
        $this->font = $this->config["chart_font_path"];
        $this->height = 0;
        $this->width = 0;
    }

    public function process(string $baseData): string
    {
        $container = [];

        $baseData = json_decode($baseData, true);

        if (!isset($baseData['hidpiRatio']) || !isset($baseData['charts'][0])) {
            throw new \RuntimeException("Incorrect json data provided!");
        }

        $hidpi = $baseData['hidpiRatio'];
        $data = $baseData['charts'][0];

        $fontsize = 10 * $hidpi;

        $color = $data['colors']['text'];
        $format = strlen($color) == 4 ? "#%1x%1x%1x" : "#%2x%2x%2x";
        list($r, $g, $b) = sscanf($color, $format);

        foreach ($data['panes'] as $pane) {
            $container[] = $this->processPane($pane, $fontsize, $r, $g, $b);
        }

        //skip $data['timeAxis'][0]['rhsStub']['content'] as not needed
        $timeContent = $this->createFromString($data['timeAxis']['content']);
        $this->height += imagesy($timeContent);

        $currentHeight = $fontsize * 3;
        $baseImage = $this->createBaseImage($currentHeight);

        foreach ($container as $contents) {
            $this->imageCopy($baseImage, $contents[0], 0, $currentHeight);
            $this->imageCopy($baseImage, $contents[1], imagesx($contents[0]), $currentHeight);
            $currentHeight += imagesy($contents[0]);
        }
        $this->imageCopy($baseImage, $timeContent, 0, $currentHeight);

        $this->createUpperLabel($baseImage, $data, $fontsize, $r, $g, $b);

        $this->createBorder($baseImage);

        $id = $this->save($baseImage);

        //returns link to picture location
        return $this->getWebPath($id);
    }

    protected function createBaseImage($currentHeight)
    {
        $baseImage = imagecreatetruecolor($this->width, $this->height + $currentHeight + 5);
        $white = imagecolorallocate($baseImage, 255, 255, 255);
        imagefill($baseImage, 0, 0, $white);

        return $baseImage;
    }

    protected function createFromString($string)
    {
        return imagecreatefromstring(base64_decode(substr($string, 22)));
    }

    protected function imageCopy($baseImage, $content, $width, $height)
    {
        imagecopy($baseImage, $content, $width, $height, 0, 0, imagesx($content), imagesy($content));
    }

    protected function createUpperLabel($baseImage, array $data, $fontsize, $r, $g, $b)
    {
        $color = imagecolorallocate($baseImage, $r, $g, $b);
        $args = array_merge($data['meta'], $data['ohlc']);
        $text = vsprintf("%s, %s, %s, %s O:%s H:%s L:%s C:%s", $args);
        imagettftext($baseImage, $fontsize, 0, $fontsize, $fontsize * 2, $color, $this->font, $text);
    }

    protected function createBorder($baseImage)
    {
        $black = imagecolorallocate($baseImage, 0, 0, 0);
        imagerectangle($baseImage, 0, 0, imagesx($baseImage) - 1, imagesy($baseImage) -1, $black);
    }

    protected function save($baseImage): string
    {
        $id = $this->getName();
        $filename = sprintf("%s/%s.png", $this->config['output_path'], $id);
        imagepng($baseImage, $filename);

        return $id;
    }
    
    protected function getName(): string
    {
        return md5(rand(0, getrandmax()));
    }
    
    protected function getWebPath(string $id): string
    {
        return $_SERVER['SERVER_NAME'] . sprintf("%s/%s.png", $this->config['server_path'], $id);
    }

    protected function processPane(array $pane, $fontsize, $r, $g, $b)
    {
        $contents = [
            $pane['content'],
            $pane['rightAxis']['content']
        ];
        $contents = array_map(
            function ($el) {
                return $this->createFromString($el);
            },
            $contents
        );

        //add labels if exist
        if (isset($pane['studies'])) {
            foreach ($pane['studies'] as $index => $study) {
                $color = imagecolorallocate($contents[0], $r, $g, $b);
                //max 6 labels in column
                $x = $fontsize + (intval($index/6) * imagesx($contents[0])/4);
                $y = ($index % 6) * 1.5 * $fontsize + 2 * $fontsize;

                imagettftext($contents[0], $fontsize, 0, $x, $y, $color, $this->font, $study);
            }
        }

        $this->height += imagesy($contents[0]);
        $tmpWidth = imagesx($contents[0]) + imagesx($contents[1]);
        if ($tmpWidth > $this->width) {
            $this->width = $tmpWidth;
        }

        return $contents;
    }
}
