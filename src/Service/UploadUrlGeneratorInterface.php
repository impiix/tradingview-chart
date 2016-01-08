<?php
/**
 * Date: 1/8/16
 * Time: 5:38 PM
 */
namespace TradingView\Service;
/**
 * Interface UploadUrlGeneratorInterface
 */
interface UploadUrlGeneratorInterface
{
    /**
     * @param string $path
     *
     * @return string
     */
    public function generateUrl($path);
}
