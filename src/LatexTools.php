<?php

class LatexTools {

  private $pathToLatexTool = '';
  private $pathToDviPngTool = '';

  private $cachePath;
  private $tempPath;

  private $density = 160;
  private $fallbackToImage = true;
  private $fallbackImageFontName = __DIR__ . '/fonts/PT_Serif-Web-Regular.ttf';
  private $fallbackImageFontSize = 16;

  function __construct($params = array()) {

    if (array_key_exists('pathToLatexTool', $params) && file_exists($params['pathToLatexTool'])) {
      $this->pathToLatexTool = $params['pathToLatexTool'];
    } else
    if (file_exists('/Library/TeX/texbin/latex')) {
      // Mac OS
      $this->pathToLatexTool = '/Library/TeX/texbin/latex';
    } else
    if (file_exists('/usr/bin/latex')) {
      // linux
      $this->pathToLatexTool = '/usr/bin/latex';
    } else {
      throw new Exception('latex not installed');
    }

    if (array_key_exists('pathToDviPngTool', $params) && file_exists($params['pathToDviPngTool'])) {
      $this->pathToDviPngTool = $params['pathToDviPngTool'];
    } else
    if (file_exists('/Library/TeX/texbin/dvipng')) {
      // Mac OS
      $this->pathToDviPngTool = '/Library/TeX/texbin/dvipng';
    } else
    if (file_exists('/usr/bin/dvipng')) {
      // linux
      $this->pathToDviPngTool = '/usr/bin/dvipng';
    } else {
      throw new Exception('dvipng not installed');
    }

    if (array_key_exists('cachePath', $params)) {
      $this->setCachePath($params['cachePath']);
    }

    if (array_key_exists('tempPath', $params)) {
      $this->setTempPath($params['tempPath']);
    }

    if (array_key_exists('density', $params)) {
      $this->density = $params['density'];
    }

    if (array_key_exists('fallbackToImage', $params)) {
      $this->fallbackToImage = $params['fallbackToImage'];
    }

    if (array_key_exists('fallbackImageFontName', $params)) {
      $this->fallbackImageFontName = $params['fallbackImageFontName'];
    }

    if (array_key_exists('fallbackImageFontSize', $params)) {
      $this->fallbackImageFontSize = $params['fallbackImageFontSize'];
    }

  }

  private function assembleParams($params = array()) {

    $result = $params;
    if (!is_array($result)) {
      $result = array();
    }
    $result['density']               = array_key_exists('density', $result) ? $result['density'] : $this->density;
    $result['fallbackToImage']       = array_key_exists('fallbackToImage', $result) ? $result['fallbackToImage'] : $this->fallbackToImage;
    $result['fallbackImageFontName'] = array_key_exists('fallbackImageFontName', $result) ? $result['fallbackImageFontName'] : $this->fallbackImageFontName;
    $result['fallbackImageFontSize'] = array_key_exists('fallbackImageFontSize', $result) ? $result['fallbackImageFontSize'] : $this->fallbackImageFontSize;
    $result['checkOnly']             = array_key_exists('checkOnly', $result) ? $result['checkOnly'] : false;
    $result['debug']                 = array_key_exists('debug', $result) ? $result['debug'] : false;

    if ($result['checkOnly']) {
      $result['fallbackToImage'] = false;
    }

    return $result;

  }

  private function getFormulaHash($formula, $params = array()) {

    $params = $this->assembleParams($params);
    $result = md5($formula . '|' . serialize($params));

    return $result;

  }

  private function HtmlToText($html) {

    $html = preg_replace('~<!DOCTYPE[^>]*?>~ism', '', $html);
    $html = preg_replace('~<head[^>]*?>.*?</head>~ism', '', $html);
    $html = preg_replace('~<style[^>]*?>.*?</style>~ism', '', $html);
    $html = preg_replace('~<script[^>]*?>.*?</script>~ism', '', $html);
    $html = preg_replace('~&nbsp;~ism', ' ', $html);
    $html = preg_replace("~<br[^>]*>[\n]+~ism", "\n", $html);
    $html = preg_replace("~<br[^>]*>~ism", "\n", $html);
    $html = preg_replace('~<[A-Z][^>]*?>~ism', '', $html);
    $html = preg_replace('~<\/[A-Z][^>]*?>~ism', '', $html);
    $html = preg_replace('~<!--.*?-->~ism', ' ', $html);
    $html = preg_replace('~^[ ]+$~ism', '', $html);
    $html = preg_replace('~^[ ]+~ism', '', $html);
    $html = preg_replace("~^(\n\r){2,}~ism", "\n", $html);
    $html = preg_replace("~^(\r\n){2,}~ism", "\n", $html);
    $html = preg_replace("~^(\n){2,}~ism", "\n", $html);
    $html = preg_replace("~^(\r){2,}~ism", "\n", $html);

    $flags = ENT_COMPAT;
    if (defined('ENT_HTML401')) {
      $flags = $flags | ENT_HTML401;
    }
    $html = html_entity_decode($html, $flags, 'UTF-8');

    return trim($html);

  }

  private function renderSimpleImage($formula, $params = array()) {

    $params = $this->assembleParams($params);
    $params['format'] = 'fallback';

    $formula = $this->HtmlToText($formula);

    $formula = str_replace('\\ ', ' ' , $formula);
    $formula = str_replace('\\\\', "\n" , $formula);

    $formulaHash = $this->getFormulaHash($formula, $params);

    if (array_key_exists('outputFile', $params)) {
      $outputFile = $params['outputFile'];
    } else {
      $outputFileName = 'latex-' . $formulaHash . '.png';
      $outputFile = $this->getCachePath() . $outputFileName;
    }

    if (file_exists($outputFile)) {
      return $outputFile;
    } else {

      if (!function_exists('imagettfbbox')) {
        throw new Exception('GD library not installed');
      }

      $fontSize = $params['fallbackImageFontSize'];
      $fontName = $params['fallbackImageFontName'];

      $formula = wordwrap($formula, 60);

      if ($box = @imagettfbbox($fontSize, 0, $fontName, $formula)) {
        $deltaY = abs($box[5]) + 2;
        $width  = $box[2];
        $height = $box[1] + $deltaY + 4;

        $image = imagecreatetruecolor($width, $height);

        try {
          if (!@imagesavealpha($image, true)) {
            throw new Exception('Alpha channel not supported');
          }

          $transparentColor = imagecolorallocatealpha($image, 0, 0, 0, 127);

          imagefill($image, 0, 0, $transparentColor);

          $black = imagecolorallocate($image, 0, 0, 0);

          $retval = @imagettftext($image, $fontSize, 0, 0, $deltaY, $black, $fontName, $formula);

          if (!$retval) {
            throw new Exception('Can not render formula using provided font');
          }

          $retval = @imagepng($image, $outputFile);

          if (!$retval || !file_exists($outputFile) || (0 === filesize($outputFile))) {
            throw new Exception('Can not save output image');
          }
        } finally {
          imagedestroy($image);
        }
      } else {
        throw new Exception('Font ' . $fontName . ' not found');
      }

      return $outputFile;

    }

  }

  private function checkImages($formula) {

    return preg_match('/\\includegraphics[{]([^:]+?):data:image\/([a-z]+);base64,([^}]+?)[}]/ism', $formula);

  }

  private function processImages($formula) {

    $result = ['formula' => $formula, 'tempFiles' => [] ];

    while(preg_match('/[\\\]includegraphics[{].*?data:image\/([a-z]+);base64,([^}]+?)[}]/ism', $result['formula'], $matches)) {
      try {
        // throw new \Exception('a');
        $imageType = $matches[1];
        $packedImage = $matches[2];
        $fileName = md5($packedImage) . '.' . $imageType;
        $filePath = $this->getTempPath() . $fileName;
        if (file_put_contents($filePath, base64_decode($packedImage))) {
          $result['tempFiles'][] = $filePath;
          if ($image = @imagecreatefrompng($filePath)) {
            $imageWidth = imagesx($image);
            $imageHeight = imagesy($image);
            $result['formula'] = str_replace($matches[0], '\includegraphics[natwidth=' . $imageWidth . ',natheight=' . $imageHeight . ']{' . $filePath . '}\\\\', $result['formula']);
          } else {
            throw new Exception('Error');
          }
        } else {
          throw new Exception('Error');
        }
      } catch (Exception $e) {
        $result['formula'] = str_replace($matches[0], '', $result['formula']);
      }
    }

    // debug($result['formula']);exit();

    return $result;

  }

  private function render($formula, $params = array()) {

    $params = $this->assembleParams($params);
    $params['format'] = 'image';

    $formula = iconv("UTF-8","ISO-8859-1//IGNORE", $formula);
    $formula = iconv("ISO-8859-1","UTF-8", $formula);

    $formula = str_replace('\\text{img_}', '', $formula);

    $images = $this->processImages($formula);

    // exit();

    $formula   = $images['formula'];
    $tempFiles = $images['tempFiles'];

    $formula = str_replace('#', '\\#', $formula);

    // $formula = wordwrap($formula, 256, '\\\\ ');

    $latexDocument  = '';
    $latexDocument .= '\documentclass{article}' . "\n";
    $latexDocument .= '\usepackage[utf8]{inputenc}' . "\n";
    $latexDocument .= '\usepackage{amsmath}' . "\n";
    $latexDocument .= '\usepackage{amsfonts}' . "\n";
    $latexDocument .= '\usepackage{amsthm}' . "\n";
    $latexDocument .= '\usepackage{amssymb}' . "\n";
    $latexDocument .= '\usepackage{amstext}' . "\n";
    $latexDocument .= '\usepackage{color}' . "\n";
    $latexDocument .= '\usepackage{pst-plot}' . "\n";
    $latexDocument .= '\usepackage{graphicx}' . "\n";
    $latexDocument .= '\begin{document}' . "\n";
    $latexDocument .= '\pagestyle{empty}' . "\n";
    $latexDocument .= '\begin{gather*}' . "\n";
    $latexDocument .= trim($formula) . "\n";
    $latexDocument .= '\end{gather*}' . "\n";
    $latexDocument .= '\end{document}'."\n";

    // debug($latexDocument);exit();
    if ($params['debug']) {
      echo('<pre>' . $latexDocument . '</pre>');
    }

    $formulaHash = $this->getFormulaHash($latexDocument, $params);

    if (array_key_exists('outputFile', $params)) {
      $outputFile = $params['outputFile'];
    } else {
      $outputFileName = 'latex-' . $formulaHash . '.png';
      $outputFile = $this->getCachePath() . $outputFileName;
    }

    if (!$params['checkOnly'] && file_exists($outputFile) && (filesize($outputFile) > 0)) {
      return $outputFile;
    } else {
      $tempFileName = 'latex-' . $formulaHash . '.tex';
      $tempFile = $this->getTempPath() . $tempFileName;
      $tempFiles[] = $tempFile;

      $auxFileName = 'latex-' . $formulaHash . '.aux';
      $auxFile = $this->getTempPath() . $auxFileName;
      $tempFiles[] = $auxFile;

      $logFileName = 'latex-' . $formulaHash . '.log';
      $logFile = $this->getTempPath() . $logFileName;
      $tempFiles[] = $logFile;

      $dviFileName = 'latex-' . $formulaHash . '.dvi';
      $dviFile = $this->getTempPath() . $dviFileName;
      $tempFiles[] = $dviFile;

      try {

        if (@file_put_contents($tempFile, $latexDocument) === false) {
          throw new Exception('Can not create temporary formula file at ' . $tempFile);
        }

        try {
          $command = 'cd ' . $this->getTempPath() . '; ' . $this->pathToLatexTool . ' --interaction=nonstopmode ' . $tempFileName . ' < /dev/null';
          $output = '';
          $retval = '';

          if ($params['debug']) {
            echo('<pre>' . $formula . '</pre>');
            echo('<pre>' . $command . '</pre>');
          }

          exec($command, $output, $retval);

          if ($params['debug']) {
            echo('<pre>');
            print_r($output);
            echo('</pre>');
          }

          $output = join('\n', $output);

          if (($retval > 0) || preg_match('/Emergency stop/i', $output) || !file_exists($dviFile) || (0 === filesize($dviFile))) {
            throw new Exception('Can not compile LaTeX formula');
          }

        } catch (Exception $e) {
          if ($params['fallbackToImage']) {
            return $this->renderSimpleImage($formula, $params);
          } else {
            throw $e;
          }
        }

        // $command = $this->pathToDviPngTool . ' -q -T tight -D ' . $params['density'] . ' -o ' . $outputFile . ' ' . $dviFile;
        $command = $this->pathToDviPngTool . ' -q ' . $params['density'] . ' -o ' . $outputFile . ' ' . $dviFile;

        $retries = 10;

        $output = '';
        $retval = '';

        if ($params['debug']) {
          echo('<pre>' . $command . '</pre>');
        }

        exec($command, $output, $retval);

        if (($retval > 0) || !file_exists($outputFile) || (0 === filesize($outputFile))) {
          if (!file_exists($outputFile) || (0 === filesize($outputFile))) {
            throw new Exception('Can not convert DVI file to PNG');
          }
        }

      } finally {

        foreach($tempFiles as $tempFile) {
          if (file_exists($tempFile)) {
            @unlink($tempFile);
          }
        }

      }

      return $outputFile;

    }

  }

  public function isValidLaTeX($formula) {

    try {
      $this->check($formula);
      return true;
    } catch (Exception $e) {
      return false;
    }

  }

  public function check($formula) {

    return $this->render($formula, ['checkOnly' => true]);

  }

  public function renderIntoFile($formula, $params = array()) {

    $imageFile = $this->render($formula, $params);

    return $imageFile;

  }

  public function renderIntoResponse($formula, $params = array()) {

    $imageFile = $this->render($formula, $params);

    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($imageFile));

    readfile($imageFile);

  }

  public function setCachePath($value) {

    $this->cachePath = rtrim($value, '/') . '/';

  }

  public function makeDir($path, $access = 0777) {

    if (file_exists($path)) {
      return true;
    }

    try {
      return @mkdir($path, $access, true);
    } catch (\Exception $e) {
      return false;
    }

  }

  public function getCachePath() {

    if ($this->cachePath) {
      $result = $this->cachePath;
    } else {
      $result = sys_get_temp_dir();
    }

    if (!is_dir($result)) {
      $this->makeDir($result, 0777);
    }

    if (!is_dir($result) || !is_writable($result)) {
      $result = rtrim(sys_get_temp_dir(), '/') . '/';
      if ($this->cachePath) {
        $result .= md5($this->cachePath) . '/';
      }
    }

    if (!is_dir($result) || !is_writable($result)) {
      $result = rtrim(sys_get_temp_dir(), '/') . '/';
    }

    return $result;

  }

  public function setTempPath($value) {

    $this->tempPath = rtrim($value, '/') . '/';

  }

  public function getTempPath() {

    if ($this->tempPath) {
      $result = $this->tempPath;
    } else {
      $result = sys_get_temp_dir();
    }

    if (!is_dir($result)) {
      $this->makeDir($result, 0777);
    }

    if (!is_dir($result) || !is_writable($result)) {
      $result = rtrim(sys_get_temp_dir(), '/') . '/';
      if ($this->tempPath) {
        $result .= md5($this->tempPath) . '/';
      }
    }

    if (!is_dir($result) || !is_writable($result)) {
      $result = rtrim(sys_get_temp_dir(), '/') . '/';
    }

    return $result;

  }

  public function setFallbackToImage($value) {

    $this->fallbackToImage = $value;

  }

  public function getFallbackToImage() {

    return $this->fallbackToImage;

  }

  public function setFallbackImageFontName($value) {

    $this->fallbackImageFontName = $value;

  }

  public function getFallbackImageFontName() {

    return $this->fallbackImageFontName;

  }

  public function setFallbackImageFontSize($value) {

    $this->fallbackImageFontSize = $value;

  }

  public function getFallbackImageFontSize() {

    return $this->fallbackImageFontSize;

  }

  public function setDensity($value) {

    $this->density = $value;

  }

  public function getDensity() {

    return $this->density;

  }

}