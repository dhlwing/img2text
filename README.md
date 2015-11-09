img2text
=======

Image to Ascii Text, can output to html or ansi terminal.



Example
-------

![](cat.jpg)

1. `img2text cat.jpg > without-color.html`
2. `img2text cat.jpg --color > with-color.html`
3. `img2text cat.jpg --ansi`

Installation
------------

```bash
$ composer require bigweb/img2text
```

Usage
-----

```
Usage:
  img2text <imgfile> [--maxLen=<n>] [--fontSize=<n>] [--color] [--ansi] [--bgcolor=<#RRGGBB>]
  img2text (-h | --help)

Options:
  -h --help             show this screen.
  --ansi                output an ANSI rendering of the image
  --color               output a colored HTML rendering of the image.
  --antialias           causes any resizing of the image to use antialiasing
  --fontSize=<n>        sets font size (in pixels) when outputting HTML,
                        default: 7
  --maxLen=<n>          resize image so that larger of width or height matches
                        maxLen, default: 100px
  --bgcolor=<#RRGGBB>   if specified, is blended with transparent pixels to
                        produce the output. In ansi case, if no bgcolor set, a
                        fully transparent pixel is not drawn at all, partially
                        transparent pixels drawn as if opaque
```

Thanks
------

Thanks @hit9, This project stolen from  https://github.com/hit9/img2txt

License
-------

BSD.
