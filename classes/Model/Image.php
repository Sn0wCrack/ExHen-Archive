<?php

class Model_Image extends Model_Abstract
{
    public function delete()
    {
        $filepath = $this->getFilepath();
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    public function getFilepath()
    {
        if ($this->id) {
            return realpath(Config::get()->imagesDir).DS.str_pad($this->id, 6, '0', STR_PAD_LEFT).'.'.$this->type;
        }
    }

    public function getUrl()
    {
        if ($this->id) {
            return '/images/'.str_pad($this->id, 6, '0', STR_PAD_LEFT).'.'.$this->type;
        }
    }

    public static function importFromFile($filepath)
    {
        if (!file_exists($filepath)) {
            throw new Exception('Input file does not exist');
        }

        $ret = getimagesize($filepath);
        if (!$ret) {
            throw new Exception('Input file is invalid');
        }

        list($width, $height, $type, $attr) = $ret;

        $image = R::dispense('image');
        R::store($image);
        $image->width = $width;
        $image->height = $height;
        $image->type = image_type_to_extension($type, false);
        $image->filename = pathinfo($filepath, PATHINFO_BASENAME);
        R::store($image);

        Log::debug('ModelImage', 'COPY FROM %s TO %s', $filepath, $image->getFilepath());
        $ret = copy($filepath, $image->getFilepath());
        if (!$ret) {
            R::trash($image);
            throw new Exception('Failed to copy image');
        }

        return $image;
    }
}
