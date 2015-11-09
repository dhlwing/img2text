<?php
namespace Bigweb\Img2text;

use Intervention\Image\ImageManagerStatic as Image;

class Img2text
{
    private $color;
    private $fileName;
    private $maxLen;
    private $fontSize;
    private $bgcolor;
    private $antialias;

    private $generateWidth;
    private $generateHeight;

    public function __construct($fileName, $options=[]) {

        if (!file_exists($fileName)) {
            die("file " .$fileName." not found!");
        }
        $this->fileName = $fileName;
        if (isset($options['maxLen']))
            $this->maxLen = $options['maxLen'];

        if (isset($options['fontSize']))
            $this->fontSize = intval($options['fontSize']);
        else
            $this->fontSize = 7;
        
    }

    public function render() {
        try {
            $img = $this->loadAndResizeImg();
        } catch(Elception $e) {
            die($e->getMessage());
        }

        $string = $this->generate_grayscale($img);

        $html = <<<'HTML'
<!DOCTYPE HTML>
<html>
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8" />
  <style type="text/css" media="all">
    pre {
      white-space: pre-wrap;       /* css-3 */
      white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
      white-space: -pre-wrap;      /* Opera 4-6 */
      white-space: -o-pre-wrap;    /* Opera 7 */
      word-wrap: break-word;       /* Internet Explorer 5.5+ */
      font-family: 'Menlo', 'Courier New', 'Consola';
      line-height: 1.0;
      font-size: %dpx;
    }
  </style>
</head>
<body>
  <pre>%s</pre>
</body>
</html>

HTML;
        return sprintf($html, $this->fontSize, $string);
    }

    public function HTMLColorToRGB($colorstring) {

    }

    public function alpha_blend() {

    }

    public function generate_grayscale($img) {
        # grayscale
        $newColor  = "MNHQ\$OC?7>!:-;. ";
        $string = "";

        for ($i = 0; $i < $this->generateHeight; $i++) { 
            for ($j = 0; $j < $this->generateWidth; $j++) { 
                $color = $img->pickColor($j, $i, 'array');  //array: array(255, 255, 255, 1) rgb: rgb(255, 255, 255)
                if ($color[3] != 255 && $this->bgcolor) {

                }
                $string .= $newColor[intval(array_sum($color) / 3.0 / 256.0 * 16)];
            }
            $string .= "\n";
        }

        return $string;
    }

    public function loadAndResizeImg() {
        $img = Image::make($this->fileName);

        $this->generateWidth  = $img->width();
        $this->generateHeight = $img->height();

        if ($this->maxLen) {
            $rate = number_format($this->maxLen / max($this->generateWidth, $this->generateHeight), 1);
            $this->generateWidth = intval($rate * $this->generateWidth);
            $this->generateHeight = intval($rate * $this->generateHeight);
            $img->resize($this->generateWidth, $this->generateHeight);
        }
        return $img;
    }
}
