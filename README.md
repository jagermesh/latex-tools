# LatexTools

Wrapper around TexLive to render LaTeX formula into images

## Requirement

### Linux

- Install TexLive from [https://www.tug.org/texlive/](https://www.tug.org/texlive/)

### Mac OS

- MacTex from [http://tug.org/mactex/](http://tug.org/mactex/)
- Install dvipng
```$ sudo tlmgr update --self```
```$ sudo tlmgr install dvipng```

## Usage

```php
<?php
      require_once(dirname(__DIR__) . '/3rdparty/LatexTools/LatexTools.php');
      $latexTools = new LatexTools();
      $latexTools->setCacheDir(dirname(__DIR__) . '/_tmp');
      $latexTools->setTempDir(dirname(__DIR__) . '/_tmp');
      $latexTools->renderIntoResponse('\sum_{i = 0}^{i = n} \frac{i}{2}');
```
