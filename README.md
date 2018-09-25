# LatexTools

Wrapper around TexLive to render LaTeX formula into images

## Requirements

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
      $latexTools->renderIntoResponse('\sum_{i = 0}^{i = n} \frac{i}{2}');
```

## Additional functions

Set cache directory

```php
$latexTools->setCacheDir(dirname(__DIR__) . '/_tmp');
```

Set cache directory

```php
$latexTools->setTempDir(dirname(__DIR__) . '/_tmp');
```


