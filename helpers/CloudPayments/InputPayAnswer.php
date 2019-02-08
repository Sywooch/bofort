<?php

/**
 * Created by PhpStorm.
 * User: dasha
 * Date: 06.02.19
 * Time: 21:14
 */

namespace app\helpers\CloudPayments;

use Yii;
use yii\base\Model;
use yii\helpers\Json;

class InputPayAnswer extends Model
{
    public $transactionId;
    public $amount;
    public $currency;
    public $dateTime;
    public $cardFirstSix;
    public $cardLastFour;
    public $cardType;
    public $cardExpDate;
    public $status;
    public $invoiceId;
    public $accountId;
    public $token;

    public function rules()
    {
        return [
            [['transactionId','amount','currency','dateTime','cardFirstSix','cardLastFour','cardType','cardExpDate','status','invoiceId','accountId','token'],'optional']
        ];
    }

    static function collect() {
<<<<<<< HEAD
	    Yii::info(Yii::$app->request->getRawBody());
	    $data = Json::decode(Yii::$app->request->getRawBody(), true);
=======
        $data = $_POST;
>>>>>>> 5a2ce735806335d3a3a374497b3b5100eb2804a7
        $out = new self;
        $out->transactionId = $data['TransactionId'];
        $out->amount = $data['Amount'];
        $out->currency = $data['Currency'];
        $out->dateTime = $data['DateTime'];
        $out->cardFirstSix = $data['CardFirstSix'];
        $out->cardLastFour = $data['CardLastFour'];
        $out->cardType = $data['CardType'];
        $out->cardExpDate = $data['CardExpDate'];
        $out->status = $data['Status'];
        $out->invoiceId = $data['InvoiceId'];
        $out->accountId = $data['AccountId'];
        $out->token = $data['Token'];
        return $out;
    }

}
