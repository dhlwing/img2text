<?php
namespace Bigweb\Img2text;

use Intervention\Image\ImageManagerStatic as Image;

class Img2text
{
    private $color;
    private $ansi;
    private $fileName;
    private $maxLen;
    private $fontSize;
    private $bgcolor;

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

        if (isset($options['color']))
            $this->color = $options['color'];
        
    }

    public function render() {
        try {
            $img = $this->loadAndResizeImg();
        } catch(Elception $e) {
            die($e->getMessage());
        }

        if ($this->ansi)
            # code here
            return $this->generate_ansi($img);
        else if ($this->color) 
            $string = $this->generate_colorful($img);
        else
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

    public static function HTMLColorToRGB($colorstring) {
        $colorstring = trim($colorstring);
        if ($colorstring[0] == '#')
            $colorstring = substr($colorstring, 1);
        if (strlen($colorstring) != 6)
            throw new Exception("input #".$zcolorstring." is not in #RRGGBB format", 1);
            
        $r = substr($colorstring, 0, 2);
        $g = substr($colorstring, 2, 2);
        $b = substr($colorstring, 4);

        return [hexdec($r), hexdec($g), hexdec($b)];
    }

    public static function alpha_blend($src, $dst) {
        # Does not assume that dst is fully opaque
        # See https://en.wikipedia.org/wiki/Alpha_compositing - section on "Alpha
        # Blending"
        $src_multiplier = ($src[3] / 255.0);
        $dst_multiplier = ($dst[3] / 255.0) * (1 - $src_multiplier);
        $result_alpha = $src_multiplier + $dst_multiplier;
        if ($result_alpha == 0) {      # special case to prevent div by zero below
            return [0, 0, 0, 0];
        } else {
            return [
                int((($src[0] * $src_multiplier) +
                    ($dst[0] * $dst_multiplier)) / $result_alpha),

                int((($src[1] * $src_multiplier) +
                    ($dst[1] * $dst_multiplier)) / $result_alpha),
                int((($src[2] * $src_multiplier) +
                    ($dst[2] * $dst_multiplier)) / $result_alpha),
                intval($result_alpha * 255)
            ];
        }
    }

    public static function getANSIcolor_for_rgb($rgb) {
        # Convert to web-safe color since that's what terminals can handle in
        # "256 color mode"
        #   https://en.wikipedia.org/wiki/ANSI_escape_code
        # http://misc.flogisoft.com/bash/tip_colors_and_formatting#bash_tipscolors_and_formatting_ansivt100_control_sequences # noqa
        # http://superuser.com/questions/270214/how-can-i-change-the-colors-of-my-xterm-using-ansi-escape-sequences # noqa
        $websafe_r = intval(round(($rgb[0] / 255.0) * 5));
        $websafe_g = intval(round(($rgb[1] / 255.0) * 5));
        $websafe_b = intval(round(($rgb[2] / 255.0) * 5));

        # Return ANSI coolor
        # https://en.wikipedia.org/wiki/ANSI_escape_code (see 256 color mode
        # section)
        return intval((($websafe_r * 36) + ($websafe_g * 6) + $websafe_b) + 16);
    }

    public static function getANSIfgarray_for_ANSIcolor($ANSIcolor) {
        $doc = "Return array of color codes to be used in composing an SGR escape
        sequence. Using array form lets us compose multiple color updates without
        putting out additional escapes";
        # We are using "256 color mode" which is available in xterm but not
        # necessarily all terminals
        # To set FG in 256 color you use a code like ESC[38;5;###m
        return ['38', '5', $ANSIcolor];
    }

    public static function getANSIbgarray_for_ANSIcolor($ANSIcolor) {
        $doc = "Return array of color codes to be used in composing an SGR escape
        sequence. Using array form lets us compose multiple color updates without
        putting out additional escapes";
        # We are using "256 color mode" which is available in xterm but not
        # necessarily all terminals
        # To set BG in 256 color you use a code like ESC[48;5;###m
        return ['48', '5', $ANSIcolor];
    }

    public static function getANSIbgstring_for_ANSIcolor($ANSIcolor) {
        # Get the array of color code info, prefix it with ESCAPE code and
        # terminate it with "m"
        return "\x1b[" + implode(";", self::getANSIbgarray_for_ANSIcolor($ANSIcolor)) + "m";
    }

    public function generate_colorful($img) {
        $string = "";
        # first go through the height,  otherwise will rotate
        for ($h = 0; $h < $this->generateHeight; $h++) { 
            for ($w = 0; $w < $this->generateWidth; $w++) { 
                $color = $img->pickColor($w, $h, 'hex'); 
                $string .= ("<span style=\"color: ".$color.";\">▇</span>");
            }
            $string .= "\n";
        }
        return $string;
    }

    public function generate_grayscale($img) {
        # grayscale
        $newColor  = "MNHQ\$OC?7>!:-;. ";
        $string = "";

        for ($h = 0; $h < $this->generateHeight; $h++) { 
            for ($w = 0; $w < $this->generateWidth; $w++) { 
                $color = $img->pickColor($w, $h, 'array');  //array: array(255, 255, 255, 1) rgb: rgb(255, 255, 255)
                if ($color[3] != 255 && $this->bgcolor) {

                }
                $string .= $newColor[intval(array_sum($color) / 3.0 / 256.0 * 16)];
            }
            $string .= "\n";
        }

        return $string;
    }

    public function generate_ansi($img) {
        # Since the "current line" was not established by us, it has been
        # filled with the current background color in the
        # terminal. We have no ability to read the current background color
        # so we want to refill the line with either
        # the specified bg color or if none specified, the default bg color.
        if ($this->bgcolor) {
            # Note that we are making the assumption that the viewing terminal
            # supports BCE (Background Color Erase) otherwise we're going to
            # get the default bg color regardless. If a terminal doesn't
            # support BCE you can output spaces but you'd need to know how many
            # to output (too many and you get linewrap)

        } else {
            # reset bg to default (if we want to support terminals that can't
            # handle this will need to instead use 0m which clears fg too and
            # then when using this reset prior_fg_color to None too

            $fill_string = "\x1b[49m";
        }

        $fill_string .= "\x1b[K"; # does not move the cursor
        echo $fill_string;

        echo $this->get_ANSI_string($img);
        # Undo residual color changes, output newline because
        # generate_ANSI_from_pixels does not do so
        # removes all attributes (formatting and colors)
        echo "\x1b[0m\n";
    }

    public static function generate_ANSI_to_set_fg_bg_colors($cur_fg_color, $cur_bg_color, $new_fg_color, $new_bg_color) {
        # This code assumes that ESC[49m and ESC[39m work for resetting bg and fg
        # This may not work on all terminals in which case we would have to use
        # ESC[0m
        # to reset both at once, and then put back fg or bg that we actually want

        # We don't change colors that are already the way we want them - saves
        # lots of file size

        # use array mechanism to avoid multiple escape sequences if we need to
        # change fg and bg
        $color_array = [];

        if ($new_bg_color != $cur_bg_color) {
            if (!$new_bg_color)
                $color_array += ['49'];      # reset to default
            else
                $color_array += self::getANSIbgarray_for_ANSIcolor($new_bg_color);
        }

        if ($new_fg_color != $cur_fg_color) {
            if (!$new_fg_color)
                $color_array += ['39'];        # reset to default
            else
                $color_array += self::getANSIfgarray_for_ANSIcolor($new_fg_color);
        }

        if (count($color_array) > 0)
            return "\x1b[" . implode(";", $color_array) . "m";
        else
            return "";
    }


    public function get_ANSI_string($img, $bgcolor_rgba=false, $is_overdraw=false) {
        $_doc = "Does not output final newline or reset to particular colors at end --
        caller should do that if desired bgcolor_rgba=None is treated as default
        background color.";

        # Compute ANSI bg color and strings we'll use to reset colors when moving
        # to next line
        if ($bgcolor_rgba) {
            $bgcolor_ANSI = self::getANSIcolor_for_rgb($bgcolor_rgba);
            # Reset cur bg color to bgcolor because \n will fill the new line with
            # this color
            $bgcolor_ANSI_string = self::getANSIbgstring_for_ANSIcolor($bgcolor_ANSI);
        } else {
            $bgcolor_ANSI = "";
            # Reset cur bg color default because \n will fill the new line with
            # this color
            # reset bg to default (if we want to support terminals that can't
            # handle this will need to instead use 0m which clears fg too and then
            # when using this reset prior_fg_color to None too
            $bgcolor_ANSI_string = "\x1b[49m";
        }

        # removes all attributes (formatting and colors) to start in a known state
        $string = "\x1b[0m";

        $prior_fg_color = false;       # this is an ANSI color not rgba
        $prior_bg_color = false;       # this is an ANSI color not rgba
        $cursor_x       = 0;

        for ($h = 0; $h < $this->generateHeight; $h++) { 
            for ($w = 0; $w < $this->generateWidth; $w++) {

                $draw_char = " ";
                $rgba = $img->pickColor($w, $h, 'array');

                # Handle fully or partially transparent pixels - but not if it is
                # the special "erase" character (None)
                $skip_pixel = false;
                if ($draw_char) {
                    $alpha = $rgba[3];
                    if ($alpha == 0) {
                        $skip_pixel = true;       # skip any full transparent pixel
                    } else if ($alpha != 255 && $bgcolor_rgba )
                        # non-opaque so blend with specified bgcolor
                        $rgba = self::alpha_blend($rgba, $bgcolor_rgba);
                }

                if (!$skip_pixel) {
                    $this_pixel_str = "";
                    # Throw away alpha channel - can still have non-fully-opaque
                    # alpha value here if bgcolor was partially transparent or if
                    # no bgcolor and not fully transparent. Could make argument to
                    # use threshold to decide if throw away (e.g. >50% transparent)
                    # vs. consider opaque (e.g. <50% transparent) but at least for
                    # now we just throw it away
                    array_pop($rgba);
                    $rgb = $rgba;
                    # If we've got the special "erase" character turn it into
                    # outputting a space using the bgcolor which if None will
                    # just be a reset to default bg which is what we want
                    if (!$draw_char) {
                        $draw_char = " ";
                        $color = $bgcolor_ANSI;
                    } else {
                        # Convert from RGB to ansi color, using closest color
                        $color = self::getANSIcolor_for_rgb($rgb);
                        # Optimization - if we're drawing a space and the color is
                        # the same as a specified bg color then just skip this. We
                        # need to make this check here because the conversion to
                        # ANSI can cause colors that didn't match to now match
                        # We cannot do this optimization in overdraw mode because
                        # we cannot assume that the bg color is already drawn at
                        # this location
                        if (!$is_overdraw &&  ($draw_char == " ") && ($color == $bgcolor_ANSI)) {
                            $skip_pixel = true;
                        }
                    }

                    if (!$skip_pixel) {

                        if (strlen($draw_char) > 1) {
                            die("Not allowing multicharacter draw strings");
                        }

                        # If we are not at the cursor x location (happens if we
                        # skip pixels) output sequence to get there
                        # This is how we implement transparency - we don't draw
                        # spaces, we skip via cursor moves
                        if ($cursor_x < $w) {
                            # **SIZE - Note that when the bgcolor is specified
                            # (not None) and not overdrawing another drawing
                            # (as in an animation case) an optimization could be
                            # performed to draw spaces rather than output cursor
                            # advances. This would use less
                            # size when advancing less than 3 columns since the min
                            # escape sequence here is len 4. Not implementing this
                            # now
                            # code to advance N columns ahead
                            $string .= "\x1b[" .($w - $cursor_x). "C";
                            $cursor_x = $w;
                        }

                        # Generate the ANSI sequences to set the colors the way we
                        # want them
                        if ($draw_char == " ") {

                            # **SIZE - If we are willing to assume terminals that
                            # support ECH (Erase Character) as specified in here
                            # http://vt100.net/docs/vt220-rm/chapter4.html we could
                            # replace long runs of same-color spaces with single
                            # ECH codes. Seems like it is only correct to do this
                            # if BCE is supported though (
                            # http://superuser.com/questions/249898/how-can-i-prevent-os-x-terminal-app-from-overriding-vim-colours-on-a-remote-syst) # noqa
                            # else "erase" would draw the _default_ background
                            # color not the currently set background color

                            # We are supposed to output a space, so we're going to
                            # need to change the background color. No, we can't
                            # output an "upper ascii" character that fills the
                            # entire foreground - terminals don't display these the
                            # same way, if at all.
                            # Since we're outputting a space we can leave the prior
                            # fg color intact as it won't be used
                            $string .= self::generate_ANSI_to_set_fg_bg_colors(
                                $prior_fg_color, $prior_bg_color, $prior_fg_color,
                                $color);
                            $prior_bg_color = $color;
                        } else {
                            # We're supposed to output a non-space character, so
                            # we're going to need to change the foreground color
                            # and make sure the bg is set appropriately
                            $string .= self::generate_ANSI_to_set_fg_bg_colors(
                                $prior_fg_color, $prior_bg_color, $color,
                                $bgcolor_ANSI);
                            $prior_fg_color = $color;
                            $prior_bg_color = $bgcolor_ANSI;
                        }
                        # Actually output the character
                        $string .= $draw_char;

                        $cursor_x = $cursor_x + 1;
                    }
                }
            }

            # Handle end of line - unless last line which is NOP because we don't
            # want to do anything to the _line after_ our drawing
            if (($h + 1) != $this->generateHeight) {

                # Reset bg color so \n fills with it
                $string .= $bgcolor_ANSI_string;
                $prior_bg_color = $bgcolor_ANSI;      # because it has been reset

                # Move to next line. If this establishes a new line in the terminal
                # then it fills the _newly established line_
                # to EOL with current bg color. However, if cursor had been moved
                # up and this just goes back down to an existing
                # line, no filling occurs
                $string .= "\n";
                $cursor_x = 0;
            }
        }
        return $string;
    }

    

    public function loadAndResizeImg() {
        $img = Image::make($this->fileName);

        $this->generateWidth  = $img->width();
        $this->generateHeight = $img->height();

        if ($this->generateWidth > 100 && !$this->maxLen) {
            $this->maxLen = 100;
        }
        if ($this->maxLen) {
            $rate = number_format($this->maxLen / max($this->generateWidth, $this->generateHeight), 1);
            $this->generateWidth = intval($rate * $this->generateWidth);
            $this->generateHeight = intval($rate * $this->generateHeight);
            $img->resize($this->generateWidth, $this->generateHeight);
        }
        return $img;
    }
}
