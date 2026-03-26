<?php

namespace App\Services;

use Sabberworm\CSS\Rule\Rule;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\Value\RuleValueList;
use Sabberworm\CSS\Value\URL;
use Sunra\PhpSimple\HtmlDomParser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;

class ParserService
{
    const FONTS_CSS_FILE = '/var/assets/fonts/';
//    const FONTS_CSS_FILE = '/var/fonts/';

   const PLACEHOLDER_NEW_LINE = '###NL###';

    private KernelInterface $kernel;
    private ConfigService $configService;
    private Request $request;

    public function __construct(
        KernelInterface $kernel,
        ConfigService   $configService,
        RequestStack    $requestStack
    )
    {
        $this->kernel = $kernel;
        $this->configService = $configService;
        $this->request = $requestStack->getCurrentRequest();
    }

    private $base_path_prefix = '/var/assets';

    public function parseHtml(string $html)
    {
        $currentUrl = $this->request->getScheme() . '://' . $this->request->getHost();
        $placeholderNewLine = self::PLACEHOLDER_NEW_LINE;
        $html = str_replace("\n", $placeholderNewLine, $html);
        $dom = HtmlDomParser::str_get_html($html);
        $data = $this->modifyLinks($dom);

        $this->saveAssets($data);

        $html = (string)$dom;

        $html = str_replace($placeholderNewLine, "\n", $html);

        $html = str_replace($this->configService->getPreviewUrl(), $currentUrl, $html);

        return str_replace("{{ site_url }}", $currentUrl, $html);

    }

    private function saveAssets($assets)
    {

        foreach ($assets as $row) {
            if (false !== $hash_pos = strpos($row['new_name'], '#')) {
                $row['new_name'] = substr($row['new_name'], 0, $hash_pos);
            }

            $assetsDir = $this->kernel->getProjectDir() . '/' . $row['dirname'];

            $this->makeDir($assetsDir);

            $saveFilePath = $assetsDir . '/' . $row['new_name'];
            if (file_exists($saveFilePath))
                continue;

            $row['link'] = str_replace(' ', '%20', $row['link']);

            try {
                file_put_contents($saveFilePath, file_get_contents($row['link']));
            } catch (\Throwable $e) {

            }

        }
    }

    private function saveContentOfResource($content, $row)
    {
        $assetsDir = $this->kernel->getProjectDir() . '/' . $row['dirname'];

        $this->makeDir($assetsDir);

        $saveFilePath = $assetsDir . '/' . $row['new_name'];
        if (file_exists($saveFilePath))
            return;

        $row['link'] = str_replace(' ', '%20', $row['link']);

        try {
            file_put_contents($saveFilePath, $content);
        } catch (\Throwable $e) {

        }
    }

    private function makeDir($path)
    {
        return is_dir($path) || mkdir($path, 0777, true);
    }

    /**
     * @param $dom
     * @return array
     */
    protected function modifyLinks($dom)
    {
        $currentUrl = $this->request->getScheme() . '://' . $this->request->getHost();
        $result = [];

        foreach ($this->getTags() as $tag => $attr_names) {
            foreach ($attr_names as $one_attr_name) {
                foreach ($dom->find($tag) as $row) {

                    if($tag == 'div'){
                        if(isset($row->attr[$one_attr_name])){
                            $cssStyle = '.template_class {'.$row->attr[$one_attr_name].'}';
                            $css = $this->replaceCss($cssStyle);

                            $cssModified = $css['css'];
                            $result = array_merge($result, $css['cache']);
                            $cssModified = str_replace('.template_class {','',$cssModified);
                            $cssModified = str_replace('}','',$cssModified);
                            $cssModified = str_replace('url("',"url('",$cssModified);
                            $cssModified = str_replace('")',"')",$cssModified);
                            $row->attr[$one_attr_name] = $cssModified;
                        }
                    }

                    if ($tag == 'style') {
                        $css = $this->replaceCss(str_replace(self::PLACEHOLDER_NEW_LINE, "\n", $row->innertext()));
                        $row->__set('innertext', $css['css']);
                        $result = array_merge($result, $css['cache']);
                        continue;
                    }
                    if (!isset($row->attr[$one_attr_name])) {
                        continue;
                    }

                    if ($tag == 'link') {

                        if($row->attr[$one_attr_name] == 'canonical') {
                            $row->attr['href'] = $currentUrl . $this->request->getPathInfo();
                        }

                        if (str_contains($row->attr[$one_attr_name], 'fonts/style.css')) {
                            $fontsCssPath = $this->saveFontsCssFile($row->attr[$one_attr_name]);
                            $row->attr[$one_attr_name] = $fontsCssPath;
                            continue;
                        }

                        if (str_contains($row->attr[$one_attr_name], 'https://fonts.bunny.net/css')) {
                            $fontsCssPath = $this->saveFontsCssFile($row->attr[$one_attr_name]);
                            $row->attr[$one_attr_name] = $fontsCssPath;
                            continue;
                        }

                        if (str_contains($row->attr[$one_attr_name], 'https://fonts.bunny.net/')) {
                            $row->attr[$one_attr_name] = $currentUrl;
                            continue;
                        }

                        if (str_contains($row->attr[$one_attr_name], '//fonts.bunny.net')) {
                            $row->attr[$one_attr_name] = '//' . $this->request->getHost();
                            continue;
                        }
                    }

                    if (strpos($row->attr[$one_attr_name], $this->configService->getDeployUrl()) === false &&
                        strpos($row->attr[$one_attr_name], $this->configService->getMediaUrl()) === false &&
                        strpos($row->attr[$one_attr_name], $this->configService->getAmazonS3EditorBuildUrl()) === false &&
                        strpos($row->attr[$one_attr_name], $this->configService->getPreviewUrl()) === false
                    ) {
                        continue;
                    }


                    if ($tag == 'a' && !preg_match('/(png|jpg|jpeg|gif)$/', $row->attr[$one_attr_name])) {

                        if (strpos($row->attr[$one_attr_name], '/', 0) == 0) {
                            $row->attr[$one_attr_name] = substr($row->attr[$one_attr_name], 1);

                        }

                        continue;
                    }


                    if ($tag == 'link' && !preg_match('/\.css|\.png|\.jpg|\.jpeg/', $row->attr[$one_attr_name])) {
                        continue;
                    }


                    if ($tag == 'meta' && !filter_var($row->attr[$one_attr_name], FILTER_VALIDATE_URL)) {
                        continue;
                    }

                    if ($tag == 'meta' && !filter_var($row->attr[$one_attr_name], FILTER_VALIDATE_URL)) {
                        continue;
                    }

                    $row->attr[$one_attr_name] = htmlspecialchars_decode($row->attr[$one_attr_name]);

                    $path = $this->getPathFromUrl($row->attr[$one_attr_name]);

                    if (is_array($path)) {
                        $new_link = '';
                        foreach ($path as $i => $one_path) {
                            $normalizedUrl = $this->normalizeUrl($row->attr[$one_attr_name]);
                            $one_path = preg_replace("/\s(.+)$/", "", trim($one_path));

                            [$path_parts, $resourceContent] = $this->handleFileExtension(pathinfo($one_path), $normalizedUrl[$i]);
                            $asset_path = $this->getDirNameByContentType($path_parts);
                            $extension = preg_replace("/\#(.+)$/", "", $path_parts['extension']);
                            $assetInfo =
                                [
                                    'link' => $normalizedUrl[$i],
                                    'dirname' => $asset_path . $path_parts['dirname'],
                                    'basename' => preg_replace("/\#(.+)$/", "", $path_parts['basename']),
                                    'extension' => $extension,
                                    'filename' => $path_parts['filename'],
                                    'new_name' => $path_parts['filename'] . "." . $path_parts['extension'],
                                    'tag' => $tag
                                ];

                            if ($resourceContent)
                                $this->saveContentOfResource($resourceContent, $assetInfo);

                            $result[] = $assetInfo;
                            $zoom = $i + 1;
                            $new_link .= $this->configService->getBaseUrl() . $asset_path . $path_parts['dirname'] . "/" . $path_parts['filename'] . "." . $path_parts['extension'] . " {$zoom}x";
                            if ($zoom == 1) {
                                $new_link .= ", ";
                            }
                        }
                        $row->attr[$one_attr_name] = trim($new_link);
                    } else {
                        $path = str_replace('%26', '&', $path);
                        [$path_parts, $resourceContent] = $this->handleFileExtension(pathinfo($path), $row->attr[$one_attr_name]);
                        $asset_path = $this->getDirNameByContentType($path_parts);
                        $extension = preg_replace("/\#(.+)$/", "", $path_parts['extension']);
                        $link = htmlspecialchars_decode($row->attr[$one_attr_name]);

                        if ($path == '/fonts/style.css') {
                            $link = str_replace(' ', '%20', $link);
                            $css = $this->replaceCss(file_get_contents($link));
                            $link = $css['css'];
                            foreach ($css['cache'] as $key => $css_value) {
                                $asset_name = $css_value['dirname'] . "/" . $css_value['new_name'];
                                $link = str_replace($asset_name, '../' . $asset_name, $link);
                            }
                            $result = array_merge($result, $css['cache']);
                        }


                        $assetInfo = [
                            'link' => $link,
                            'dirname' => $asset_path . $path_parts['dirname'],
                            'basename' => preg_replace("/\#(.+)$/", "", $path_parts['basename']),
                            'extension' => $extension,
                            'filename' => $path_parts['filename'],
                            'new_name' => $path_parts['filename'] . "." . $path_parts['extension'],
                            'tag' => $tag
                        ];

                        if ($resourceContent)
                            $this->saveContentOfResource($resourceContent, $assetInfo);

                        $result[] = $assetInfo;

                        $row->attr[$one_attr_name] = $this->configService->getBaseUrl() . $asset_path . $path_parts['dirname'] . "/" . $path_parts['filename'] . "." . $path_parts['extension'];

                    }
                }
            }
        }

        return $result;
    }


    static function getPageAssetsPath($pathInfo)
    {
        //TODO: Save by page will be enabled in the moment we solve problem with global block in cloud
//        if ($pathInfo == '/') {
//            return '/_home';
//        }
//        return '/' . str_replace('/', '_', $pathInfo);

        return '';
    }


    /**
     * @param $css
     * @return array
     */
    protected function replaceCss($css)
    {
        $oCssParser = new \Sabberworm\CSS\Parser($css);
        $oCssDocument = $oCssParser->parse();
        $result = [];


        /**
         * @var DeclarationBlock $oRuleSet
         */
        foreach ($oCssDocument->getAllRuleSets() as $oRuleSet) {
            /**
             * @var Rule $rule
             */
            foreach ($oRuleSet->getRules() as $rule) {

                $rules = $this->getUrlFromCssRuleValues($rule->getValue());

                if (!count($rules)) {
                    continue;
                }

                foreach ($rules as $ruleValue) {

                    //TODO: check why we removed spaces
//                    $url = str_replace(' ', '',$ruleValue->getUrl()->getString());
                    $url = str_replace(' ', ' ',$ruleValue->getUrl()->getString());
                    if (!filter_var($url, FILTER_VALIDATE_URL)) {
                        continue;
                    }

                    $prefix1 = 'url("';
                    $prefix2 = '")';

                    $path = $this->getPathFromUrl($url);
                    $path = str_replace($prefix1, '', str_replace($prefix2, '', $path));
                    [$path_parts, $resourceContent] = $this->handleFileExtension(pathinfo($path), $url);
                    $asset_path = $this->getDirNameByContentType($path_parts);
                    $assetInfo = [
                        'link' => str_replace($prefix1, '', str_replace($prefix2, '', $url)),
                        'dirname' => $asset_path . $path_parts['dirname'],
                        'basename' => preg_replace("/\#(.+)$/", "", $path_parts['basename']),
                        'extension' => $path_parts['extension'],
                        'filename' => $path_parts['filename'],
                        'new_name' => $path_parts['filename'] . "." . $path_parts['extension']
                    ];

                    if ($resourceContent)
                        $this->saveContentOfResource($resourceContent, $assetInfo);

                    $result[] = $assetInfo;
//TODO: see why we removed spaces
//                    $value = str_replace($url, $this->configService->getBaseUrl() . $asset_path . $path_parts['dirname'] . "/" . $path_parts['filename'] . "." . $path_parts['extension'], str_replace(' ', '',$rule->getValue()));
                    $value = str_replace($url, $this->configService->getBaseUrl() . $asset_path . $path_parts['dirname'] . "/" . $path_parts['filename'] . "." . $path_parts['extension'], $rule->getValue());
                    $rule->setValue($value);
                }
            }
        }
        return [
            'cache' => $result,
            'css' => $oCssDocument->render()
        ];
    }

    private function getUrlFromCssRuleValues($ruleValue)
    {
        $result = [];
        if ($ruleValue instanceof URL) {
            return [$ruleValue];
        } elseif ($ruleValue instanceof RuleValueList) {
            foreach ($ruleValue->getListComponents() as $component) {
                if ($component instanceof RuleValueList) {
                    $result = array_merge($result, $this->getUrlFromCssRuleValues($component));
                } elseif ($component instanceof URL) {
                    $result = array_merge($result, [$component]);
                } else {
                    continue;
                }
            }
        }

        return $result;
    }


    /**
     * @param $path_parts
     * @return string
     */
    protected function getDirNameByContentType($path_parts)
    {

        $ext = preg_replace("/\#(.+)$/", "", $path_parts['extension']);
        if (in_array($ext, ['png', 'jpg', 'gif', 'jpeg'])) {
            return $this->base_path_prefix . $this->getPageAssetsPath($this->request->getPathInfo()) . '/img';
        } elseif (in_array($ext, ['svg'])) {
            return $this->base_path_prefix . $this->getPageAssetsPath($this->request->getPathInfo()) . '/svg';
        } elseif (in_array($ext, ['ttf', 'eot', 'woff', 'woff2'])) {
//            return $this->base_path_prefix . $this->getPageAssetsPath($this->request->getPathInfo()) . '/fonts';
            return '/var/fonts';
        }
        return $this->base_path_prefix . $this->getPageAssetsPath($this->request->getPathInfo());
    }

    private function handleFileExtension($path_parts, $url)
    {
        $buffer = null;
        if (!isset($path_parts['extension'])) {
            $url = str_replace(' ', '%20', $url);
            $buffer = file_get_contents($url);
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $path_parts['extension'] = explode('/', $finfo->buffer($buffer))[1];
        }

        return [$path_parts, $buffer];
    }

    /**
     * @param $url
     * @return array
     */
    private function getPathFromUrl($url)
    {
        $url = preg_replace('/(http|https):\/\/(([\w-]+\.)+[\w-]+)/', '', $url);
        $urls = explode(',', $url);

        $urls = $this->trimQueryStringFromUrls($urls);
        if (count($urls) == 1) {
            return $urls[0];
        }

        return $urls;
    }

    private function trimQueryStringFromUrls($urls)
    {
        foreach ($urls as $i => $url) {
            $urls[$i] = preg_replace('/\?.*/', '', $url);
        }

        return $urls;
    }

    private function normalizeUrl($url)
    {
        $urls = explode(',', $url);
        foreach ($urls as $i => $row) {
            $row = preg_replace("/(.*)\/(.*)\.(.*)$/", "$1/image.$3", $row);
            $urls[$i] = htmlspecialchars_decode(preg_replace("/\s(.+)$/", "", trim($row)));
        }

        if (count($urls) == 1) {
            return $urls[0];
        }

        return $urls;
    }

    protected function getTags()
    {
        return [
            'div' => ['style'],
            'script' => ['src'],
            'link' => ['href','rel'],
            'use' => ['xlink:href', 'href'],
            'img' => ['src', 'srcset'],
            'source' => ['srcset'],
            'style' => [null],
            'a' => ['href'],
//            'meta' => ['content']
        ];
    }

    public function removeNotAllowedQueryParams()
    {
        $queryNameList = array_keys($this->request->query->all());

        $queriesToBeDeleted = array_diff($queryNameList, $this->configService->getAllowedQueryParams());

        $this->request->query->add(['native_url' => 1]);

        if (!count($queriesToBeDeleted)) {
            $this->request->overrideGlobals();
            return;
        }

        foreach (array_values($queriesToBeDeleted) as $queryParam) {
            $this->request->query->remove($queryParam);
        }


        $this->request->overrideGlobals();
    }


    private function saveFontsCssFile($fontsBunnyUrl)
    {

        $fontsBunnyUrlParsed = parse_url($fontsBunnyUrl);

        $fontsCssFilePath = $this->configService->getBaseUrl() . self::FONTS_CSS_FILE . 'fonts_' . md5($fontsBunnyUrlParsed['query']) . '.css';

        if (file_exists($this->kernel->getProjectDir() . $fontsCssFilePath)) {
            return $fontsCssFilePath;
        }

        $query = explode('&', $fontsBunnyUrlParsed['query']);
        foreach ($query as $j => $value) {
            $value = explode('=', $value, 2);
            if (count($value) == 2)
                $query[$j] = urlencode($value[0]) . '=' . urlencode($value[1]);
            else
                $query[$j] = urlencode($value[0]);
        };

        $queryParams = implode('&', $query);
        $normalizedUrl = $fontsBunnyUrlParsed['scheme'] . '://' . $fontsBunnyUrlParsed['host'] . $fontsBunnyUrlParsed['path'] . '?' . $queryParams;

        $this->makeDir($this->kernel->getProjectDir() . $this->configService->getBaseUrl() . self::FONTS_CSS_FILE);
        $css = $this->replaceCss(file_get_contents($normalizedUrl));
        $this->saveAssets($css['cache']);
        file_put_contents($this->kernel->getProjectDir() . $fontsCssFilePath, $css['css'],);

        return $fontsCssFilePath;
    }


}