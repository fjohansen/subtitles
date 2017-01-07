<?php namespace Done\SubtitleConverter;

use Illuminate\Support\Facades\Response;
use Done\SubtitleConverter\SrtConverter;
use Done\SubtitleConverter\StlConverter;


interface SubtitleContract {

    // input
    public static function loadFile($path, $extension = null);
    public static function loadString($string, $extension);

    // chose format
    public function convertTo($format);

    // only text from file (without timestamps)
    public function getOnlyTextFromInput();

    // output
    public function toFile($path);
    public function toString();
//    public function download($filename);
}


class SubtitleConverter implements SubtitleContract {

    protected $input;
    protected $input_format;

    protected $parsed_data;

    protected $converter;
    protected $output;

    protected static $supported_file_extensions = [
        'srt',
        'stl',
    ];

    public static function loadFile($path, $extension = null)
    {
        $string = file_get_contents($path);
        if (!$extension) {
            $extension = fileExtension($path);
        }

        return self::loadString($string, $extension);
    }

    public static function loadString($text, $extension)
    {
        if (!in_array($extension, self::$supported_file_extensions)) {
            throw new \Exception('unsupported format');
        }

        $converter = new self;
        $converter->input = self::removeUtf8Bom($text);
        $converter->input_format = $extension;

        $input_converter = self::getConverter($extension);
        $converter->parsed_data = $input_converter->parse($converter->input);

        return $converter;
    }

    public function convertTo($extension)
    {
        $converter = self::getConverter($extension);

        $this->output = $converter->convert($this->parsed_data);

        return $this;
    }

    public function download($filename)
    {
        return Response::make($this->output, '200', array(
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ));
    }

    public function toString()
    {
        return $this->output;
    }

    public function toFile($path)
    {
        file_put_contents($path, $this->toString());
    }

    public function getOnlyTextFromInput()
    {
        $text = '';
        $data = $this->parsed_data;
        foreach ($data as $row) {
            foreach ($row['lines'] as $line) {
                $text .= $line . "\n";
            }
        }

        return $text;
    }

    // -------------------------------------- private ------------------------------------------------------------------

    public static function removeUtf8Bom($text)
    {
        $bom = pack('H*','EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);

        return $text;
    }

    private static function getConverter($extension)
    {
        if ($extension == 'stl') {
            return new StlConverter();
        } elseif ($extension == 'srt') {
            return new SrtConverter();
        }

        throw new \Exception('unknown format');
    }
}