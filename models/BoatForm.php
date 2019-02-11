<?php
/**
 * Created by PhpStorm.
 * User: dasha
 * Date: 22.01.19
 * Time: 20:20
 */

namespace app\models;


use Yii;
use yii\base\Model;
use yii\imagine\Image;
use yii\web\UploadedFile;

class BoatForm extends Model
{
    public $name, $description, $price, $engine_power, $spaciousness, $certificate, $location, $short_description, $images;

    /**
     * @return array the validation rules.
     */
    public function rules()
    {
        return [
            [['name', 'description', 'price', 'engine_power', 'spaciousness', 'certificate', 'location', 'short_description'], 'required'],
            ['price', 'number'],
            [['images'], 'file', 'maxFiles' => 10, 'extensions' => 'png, jpg, jpeg'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'name' => 'Имя',
            'description' => 'Описание',
            'price' => 'Цена',
            'engine_power' => 'Мощность',
            'spaciousness' => 'Вместимость',
            'certificate' => 'Сертификаты',
            'location' => 'Расположение',
            'short_description' => 'Короткое описание для главной',
            'images' => 'Фотографии'
        ];
    }

    public function save() {
        $boat = new BoatsModel();
        foreach (array_keys($this->getAttributes()) as $attribute)
            if ($attribute != 'images') $boat->$attribute = $this->$attribute;

        $boat->save();

        foreach ($this->images as $img) {
            $image = new ImagesModel();
            $image->path = "{$img->baseName}.{$img->extension}";
            $boat->link('images', $image);
        }

        return $boat->id;
    }

    public function upload(){
        foreach ($this->images as $image) {
            $path = Yii::$app->params['uploadsPath'] . "origin/{$image->baseName}.{$image->extension}";
            $image->saveAs($path);
            Image::thumbnail($path, 250, 150)->save(Yii::$app->params['uploadsPath']."250X150/{$image->baseName}.{$image->extension}", ['quality' => 80]);
            Image::thumbnail($path, 350, 200)->save(Yii::$app->params['uploadsPath']."250X150/{$image->baseName}.{$image->extension}", ['quality' => 80]);
            Image::thumbnail($path, 550, 350)->save(Yii::$app->params['uploadsPath']."250X150/{$image->baseName}.{$image->extension}", ['quality' => 80]);
        }
        return true;
    }

    public function loadData($arModel){
        foreach (array_keys($this->getAttributes()) as $attribute)
            $this->$attribute = $arModel->$attribute;

    }
}
