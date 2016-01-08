<?php
/**
 * Date: 1/8/16
 * Time: 5:38 PM
 */
namespace TradingView\Service;

use TradingView\Document\Image;
/**
 * Interface ImageManagerInterface
 */
interface ImageManagerInterface
{
    /**
     * @param Image $image
     */
    public function saveImage($image);
}
