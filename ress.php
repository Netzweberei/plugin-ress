<?php

/**
 * This file is part of Herbie.
 *
 * (c) Thomas Breuss <www.tebe.ch>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace herbie\plugin\ress;

use Herbie\DI;
use Herbie\Hook;

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

class RessPlugin
{
    /**
     * @var int
     */
    private static $instances = 0;

    /**
     * @var Twig_Environment
     */
    private $twig;

    private $config;

    private $alias;

    private $vw = 320;

    private $srcset = '';

    private $srcsetx = '';

    private $ctrlrecur = 0;

    public function __construct()
    {
        $this->config = DI::get('Config');
        $this->alias = DI::get('Alias');
    }

    public function install()
    {
        $this->provideAsset($this->config->get('plugins.path') . '/ress/assets/picturefill.min.js');
        $this->provideAsset($this->config->get('plugins.path') . '/ress/assets/ress.php');
        Hook::attach('twigInitialized', [$this, 'onTwigInitialized']);
        Hook::attach('pageLoaded', [$this, 'onPageLoaded']);
        Hook::attach('outputGenerated', [$this, 'onOutputGenerated']);
        Hook::attach('renderContent', [$this, 'onContentSegmentRendered'], 99);
    }

    public function onPageLoaded($page)
    {
        if(array_key_exists('vw', $_SESSION) && $_SESSION['vw']!==null) {
            $page->setData(['vw'=>$_SESSION['vw']]);
        }

    }

    private function isAsset()
    {
        $pinfo = pathinfo(@$_SERVER['REQUEST_URI']);
        switch(@$pinfo['extension']){
            case '':
                return false;
        }
    }

    public function onTwigInitialized($twig)
    {
        if( !$this->isAsset() && !$this->isSpider() ) {
            $this->setSrcset($this->config->get('plugins.config.ress.vw'));
        } else {
            return;
        }

        // Register detected viewport-size
        $this->ctrlrecur++;
        if($this->ctrlrecur == 1 &&  isset($_SESSION['request']) && $_SESSION['request']!==null ){
            $_SESSION['vw'] = $_SESSION['request'];
            $_SESSION['reload'] = 0;
        }

        $twig->addFunction(
            new \Twig_SimpleFunction('ress', [$this, 'ress'], ['is_safe' => ['html']])
        );

    }

    public function onContentSegmentRendered($content){

        $pData = DI::get('Page')->toArray()['data'];
        if(
            $this->ctrlrecur >= 1
            && array_key_exists('ress', $pData)
            && DI::get('Page')->toArray()['data']['ress'] == true
        ){
            // Search for all html-img-tags
            preg_match_all('/(?<![\"\'])<img[^>]+src=[\"\']{1}(.+)[\"\']{1}[^>]+>(?![\"\'])/U', $content, $_imgtags);
            if(count($_imgtags[1])>0){

                // prepare the images
                $ressstrtr = [];
                $imagine = new \herbie\plugin\imagine\classes\ImagineExtension($this->config, '/');
                $imagineFilter = (@$this->config->get("plugins.config.imagine.filter_sets.ress{$_SESSION['vw']}"))
                    ? "ress{$_SESSION['vw']}"
                    : 'ressMax';

                if(array_key_exists('vw', $_SESSION)){
                    $cssWidth = $this->config->get('plugins.config.ress.cssWidth');
                    $cssHeight = $this->config->get('plugins.config.ress.cssHeight');
                    $userWidth = false;
                    $userHeight = false;
                    foreach($_imgtags[1] as $_ctr => $_src){
                        $_resolvedSrc = $this->resolve($_src);
//                        var_dump($_resolvedSrc);
                        if(strpos($_resolvedSrc, 'cache')!==false){
//                            die(var_dump($_resolvedSrc));
                            continue;
                        }
                        $ressstrtr[$_imgtags[0][$_ctr]] = $imagine->imagineFunction($_resolvedSrc,$imagineFilter, $attributes = [], $alt = $_src, $class = "ress{$_SESSION['vw']}", $id = '', $style= $cssWidth.$cssHeight,
                            $title = '', $width = $userWidth ? $userWidth : 0, $height = $userHeight ? $userHeight : 0, $media = 0);
                    }
                }

                return strtr($content, $ressstrtr);
            }
        }
        return $content;
    }

    public function onOutputGenerated($response)
    {
        if($this->isSpider()) return;

        if( !isset($_SESSION['reload']) ){
            $_SESSION['reload'] = 0;
            $_SESSION['reloadinfo'] = 0;
        }

        if( $_SESSION['reload'] > 0){
            $_SESSION['reload'] = 0;
            if($this->config->get('plugins.config.ress.reload')) {
                $_SESSION['reloadinfo'] += 1;
                header("Refresh: 0; url=.");
            }
        }

        if( !isset($_SESSION['vw']) || (isset($_REQUEST['vw']) && $_REQUEST['vw']!='' && $_REQUEST['vw'] != $_SESSION['request']) ) {

            $_SESSION['request'] = $this->vw;

            $html = $response->getContent();
            $head = substr($html, 0, strpos($html, '</head>'));
            $response->setContent(
$head.'
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no">
<meta http-equiv="refresh" content="2; URL=.">
</head>
<body>
<style>.vwdetector {display:none;}</style>
<img class="vwdetector" srcset="'.$this->srcsetx.'" sizes="100vw" width="100%" height="1" alt="" />
<img class="vwdetector" srcset="'.$this->srcset.'" sizes="100vw" width="100%" height="1" alt="" onerror="this.onerror=null;'
.(($this->config->get('plugins.config.ress.reload') == true ) ? 'location.reload();' : '')
.'" />
</body>
</html>'
            );
            $this->withEventSetPicturefill($response);
        }

    }

    public function ress($params = []) {

        if(isset($params['vw'])){
            $this->setSrcset($params['vw']);
        }

        $ret = "<style>.vwdetector {display:none;}</style>";
        if(!$this->isSpider() && $this->config->get('plugins.config.ress.test') == 1) {
            $ret .= '<img class="vwdetector" srcset="'.$this->srcsetx.'" sizes="100vw" width="100%" height="0" alt="" />';
            $ret .= '<img class="vwdetector" srcset="'.$this->srcset.'" sizes="100vw" width="100%" height="0" alt="" onerror="this.onerror=null;';
            $ret .= (($this->config->get('plugins.config.ress.reload') == true ) ? 'location.reload();' : '');
            $ret .= '" />';
        }
        if(isset(DI::get('Page')->vw) && $this->config->get('plugins.config.ress.info') == 1) {
            $ret .= 'RESS-Info: Initial max viewport-width <= '.DI::get('Page')->vw.'px, density = '.@$_SESSION['density'].' ( Page reloaded '.$_SESSION['reloadinfo'].' time(s) )';
            $_SESSION['reloadinfo'] = 0;
        }

        return $ret;
    }

    public function provideAsset($uri)
    {
        // include a path:
        $pathinfo = pathinfo($uri);
        $webdir = strtr(
            dirname($uri),
            array(
                $this->alias->get('@plugin') => ''
            )
        );

        if (strpos($webdir, '://') > 1) {
            $pathPrefix = '';
        } else {
            $pathPrefix = DS . 'assets';

            // copy src to assets
            $webpath = $pathPrefix . $webdir . DS . $pathinfo['basename'];
            $abspath = $this->alias->get('@web') . $webpath;
            if (!file_exists($abspath)) {
                @mkdir(dirname($abspath), 0777, true);
                copy($uri, $abspath);
            }
        }
        return [$pathPrefix, $webdir . DS, $pathinfo['filename'], '.' . $pathinfo['extension']];
    }

    private function setSrcset($vw = []){

        if(!is_array($vw)) return '';

        $this->vw = reset($vw);

        foreach($vw as $_src){

            $_srcset[] = '/assets/ress/assets/ress.php?vw='.$_src.' '.$_src.'w';
        }
        $this->srcset = implode(',', $_srcset);
        $this->srcsetx .= '/assets/ress/assets/ress.php?density=1 1x';
        $this->srcsetx .= ',/assets/ress/assets/ress.php?density=2 2x';
        $this->srcsetx .= ',/assets/ress/assets/ress.php?density=3 3x';
    }

    private function withEventSetPicturefill($response){

        $content = $response->getContent();
        $replacements = ['</head>' => '<script src="/assets/ress/assets/picturefill.min.js"></script></head>'];
        $content = strtr($content, $replacements);
        $response->setContent($content);
    }

    private function resolve($path)
    {
        // resolve page-paths...
        $path = $this->alias->get($path);
        // ...and media-path
        $path = ltrim($path, '/');
        // webroot
        return ( strpos($path, 'media') === 0) ? $path : '/'.$path;
    }

    private function isSpider(){
        return preg_match('(bingbot|bot|borg|google(^tv)|yahoo|slurp|msnbot|msrbot|openbot|archiver|netresearch|lycos|scooter|altavista|teoma|gigabot|baiduspider|blitzbot|oegp|charlotte|furlbot|http%20client|polybot|htdig|ichiro|mogimogi|larbin|pompos|scrubby|searchsight|seekbot|semanticdiscovery|silk|snappy|speedy|spider|voila|vortex|voyager|zao|zeal|fast\-webcrawler|converacrawler|dataparksearch|findlinks|crawler|Netvibes|Sogou Pic Spider|ICC\-Crawler|Innovazion Crawler|Daumoa|EtaoSpider|A6\-Indexer|YisouSpider|Riddler|DBot|wsr\-agent|Xenu|SeznamBot|PaperLiBot|SputnikBot|CCBot|ProoXiBot|Scrapy|Genieo|Screaming Frog|YahooCacheSystem|CiBra|Nutch)', $_SERVER["HTTP_USER_AGENT"] )
            ? true
            : false;
    }
}

(new RessPlugin)->install();