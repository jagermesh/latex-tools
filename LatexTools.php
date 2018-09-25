<?php

class LatexTools {

  private $pathToLatexTool = '';
  private $pathToDviPngTool = '';

  private $cacheDir = '/tmp';
  private $tempDir = '/tmp';

  private $density = 160;
  private $fallbackToImage = true;
  private $fallbackImageFontName = __DIR__ . '/fonts/PlayfairDisplay-Regular.ttf';
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

    if (array_key_exists('cacheDir', $params)) {
      $this->setCacheDir($params['cacheDir']);
    }

    if (array_key_exists('tempDir', $params)) {
      $this->setTempDir($params['tempDir']);
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

    return $result;

  }

  private function getFormulaHash($formula, $params = array()) {

    $params = $this->assembleParams($params);
    $result = md5($formula . '|' . serialize($params));

    return $result;

  }

  private function renderSimpleImage($formula, $params = array()) {

    $params = $this->assembleParams($params);

    $formulaHash = $this->getFormulaHash($formula, $params);

    if (array_key_exists('outputFile', $params)) {
      $outputFile = $params['outputFile'];
    } else {
      $outputFileName = 'latex-' . $formulaHash . '.png';
      $outputFile = rtrim($this->cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $outputFileName;
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
        // debug($box);exit();
        $deltaY = abs($box[5]);
        $width = $box[2];
        $height = $box[1] + $deltaY;
        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);

        $transparentColor = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefill($image, 0, 0, $transparentColor);

        $black = imagecolorallocate($image, 0, 0, 0);

        imagettftext($image, $fontSize, 0, 0, $deltaY, $black, $fontName, $formula);

        imagepng($image, $outputFile);

        imagedestroy($image);
      } else {
        throw new Exception('Font ' . $fontName . ' not found');
      }

      return $outputFile;

    }

  }

  private function render($formula, $params = array()) {

    $params = $this->assembleParams($params);

    $formulaHash = $this->getFormulaHash($formula, $params);

    if (array_key_exists('outputFile', $params)) {
      $outputFile = $params['outputFile'];
    } else {
      $outputFileName = 'latex-' . $formulaHash . '.png';
      $outputFile = rtrim($this->cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $outputFileName;
    }

    if (file_exists($outputFile)) {
      return $outputFile;
    } else {
      $tempFileName = 'latex-' . $formulaHash . '.tex';
      $tempFile = rtrim($this->tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $tempFileName;

      $auxFileName = 'latex-' . $formulaHash . '.aux';
      $auxFile = rtrim($this->tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $auxFileName;

      $logFileName = 'latex-' . $formulaHash . '.log';
      $logFile = rtrim($this->tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $logFileName;

      $dviFileName = 'latex-' . $formulaHash . '.dvi';
      $dviFile = rtrim($this->tempDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $dviFileName;

      try {

        try {
          $latexDocument  = '\documentclass[12pt]{article}' . "\n";
          $latexDocument .= '\usepackage[utf8]{inputenc}' . "\n";
          $latexDocument .= '\usepackage{amssymb,amsmath}' . "\n";
          $latexDocument .= '\usepackage{color}' . "\n";
          $latexDocument .= '\usepackage{amsfonts}' . "\n";
          $latexDocument .= '\usepackage{amssymb}' . "\n";
          $latexDocument .= '\usepackage{pst-plot}' . "\n";
          $latexDocument .= '\begin{document}' . "\n";
          $latexDocument .= '\pagestyle{empty}' . "\n";
          $latexDocument .= '\begin{displaymath}' . "\n";
          $latexDocument .= $formula . "\n";
          $latexDocument .= '\end{displaymath}'."\n";
          $latexDocument .= '\end{document}'."\n";

          if (file_put_contents($tempFile, $latexDocument) === false) {
            throw new Exception('Can not create temporary formula file at ' . $tempFile);
          }

          $command = 'cd ' . $this->tempDir . '; ' . $this->pathToLatexTool . ' ' . $tempFileName . ' < /dev/null';
          $output = '';
          $retval = '';

          exec($command, $output, $retval);

          $output = join('\n', $output);

          if (($retval > 0) || preg_match('/Emergency stop/i', $output) || !file_exists($dviFile)) {
            throw new \Exception('Unable to compile LaTeX formula' );
          }

          $command = $this->pathToDviPngTool . ' -q -T tight -D ' . $params['density'] . ' -o ' . $outputFile . ' ' . $dviFile;
          $output = '';
          $retval = '';

          exec($command, $output, $retval);

          if (($retval > 0) || !file_exists($outputFile)) {
            throw new \Exception('Unable to convert DVI file to PNG');
          }

        } catch (Exception $e) {
          if ($params['fallbackToImage']) {
            return $this->renderSimpleImage($formula, $params);
          } else {
            throw $e;
          }
        }

      } finally {

        if (file_exists($tempFile)) {
          unlink($tempFile);
        }
        if (file_exists($auxFile)) {
          unlink($auxFile);
        }
        if (file_exists($logFile)) {
          unlink($logFile);
        }
        if (file_exists($dviFile)) {
          unlink($dviFile);
        }

      }

      return $outputFile;

    }

  }

  public function renderIntoImage($formula, $params = array()) {

    $imageFile = $this->render($formula, $params);

    return $imageFile;

  }

  public function renderIntoResponse($formula, $params = array()) {

    $imageFile = $this->render($formula, $params);

    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($imageFile));

    readfile($imageFile);

  }

  public function setCacheDir($value) {

    $this->cacheDir = $value;

  }

  public function getCacheDir() {

    return $this->cacheDir;

  }

  public function setTempDir($value) {

    $this->tempDir = $value;

  }

  public function getTempDir() {

    return $this->tempDir;

  }

  public function setFallbackToImage($value) {

    $this->fallbackToImage = $value;

  }

  public function getFallbackToImage() {

    return $this->fallbackToImage;

  }

  public function setDensity($value) {

    $this->density = $value;

  }

  public function getDensity() {

    return $this->density;

  }

}