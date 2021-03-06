<?php
/**
 * Created by PhpStorm.
 * User: dasha
 * Date: 04.01.19
 * Time: 12:53
 */

namespace app\controllers;


use app\helpers\CloudPayments\InputPayAnswer;
use app\models\CardsModel;
use app\models\OrdersModel;
use app\models\PayForm;
use app\models\PromoHistoryModel;
use app\models\TransactionsModel;
use CloudPayments\Exception\PaymentException;
use Exception;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\widgets\ActiveForm;

class PaymentController extends Controller
{

    public function beforeAction($action)
    {
        if ($action->id == 'complete' || $action->id == 'fail') {
            Yii::$app->controller->enableCsrfValidation = false;
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        }
        return parent::beforeAction($action);
    }

    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => ['pay', 'pay-validate'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => ['complete', 'fail'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                ],
            ],
        ];
    }

    // TODO очень сложно
    public function actionPay()
    {
        $post = Yii::$app->request->post();
        $form = new PayForm();
        $form->load($post);

        if ($form->validate()) {
            $order = OrdersModel::findOne($form->order_id);
            $order->isOfferProcessing();
            $card = CardsModel::find()->where(['user_id' => Yii::$app->user->getId(), 'state' => 1])->one();

            Yii::info('Попытка оплатить заказ ['.$order->id.']', 'app.payment.pay');
            Yii::info('Попытка оплатить заказ ['.$order->id.']', 'app.sendMail');

            if ($card) {
               try {

                   Yii::info('Привязана карта ['.$card->id.']', 'app.payment.pay');

                   $transaction = new TransactionsModel();
                   $transaction->create($form->order_id, $order->totalPrice(), Yii::$app->user->getId(), $card->id);

                   $client = new \CloudPayments\Manager(Yii::$app->params['cloud_id'], Yii::$app->params['cloud_private_key']);
                   $response = $client->chargeToken($transaction->total_price, 'RUB', Yii::$app->user->getId(), $card->token, ['InvoiceId' => $form->order_id]);

//                   $transaction->card_id = $card->id;
//                   $transaction->state = 1;
//                   $transaction->cloud_transaction_id = $response->getId();
//                   $transaction->save();

                   if ($order->promo_id != 0) {
                       PromoHistoryModel::create($order->id, Yii::$app->user->getId(), $order->promo_id);
                   }

                   Yii::info('Успешное списание денег ['.$transaction->id.']', 'app.payment.pay');

                   return $this->asJson(
                       [
                           'success' => true,
                           'action' => 'charge',
                           'data' => $response->getId()
                       ]);

               } catch (PaymentException $e) {
                   Yii::error($e->getMessage(), 'app.payment.pay');

                   $transaction->card_id = $card->id;
                   $transaction->state = -1;
//                   $transaction->cloud_transaction_id = $response->getId();
                   $transaction->save();

                   return $this->asJson(
                       [
                           'success' => false,
                           'action' => 'charge',
                           'data' => $e->getCardHolderMessage()
                       ]);
               }
            }


            Yii::info('Переход на платежный фрейм ['.$order->id.']', 'app.payment.pay');

            return $this->asJson(
                [
                    'success' => true,
                    'action' => 'frame',
                    'data' => [
                        'order_id' => $order->id,
                        'total_price' => $order->totalPrice(),
                        'user_id' => Yii::$app->user->getId()
                    ]
                ]);
        }

        return $this->asJson(
            [
                'success' => false,
            ]);
    }

    public function actionPayValidate()
    {
        $post = Yii::$app->request->post();
        $form = new PayForm();
        $form->load($post);
        Yii::$app->response->format = Response::FORMAT_JSON;
        return ActiveForm::validate($form);
    }

    /**
     * Callback от cloudpayment об успешной оплате
     * @return array
     */
    public function actionComplete()
    {
        if (Yii::$app->getRequest()->getMethod() == 'POST') {

            Yii::info('Cloudpayment answer ['.json_encode($_POST).']', 'app.payment.complete');
            Yii::info('Cloudpayment answer ['.json_encode($_POST).']', 'app.sendMail');

            $input = InputPayAnswer::collect();
            try {

                $card = CardsModel::createCardIFNoExist($input);
                Yii::info('Используется карта ['.$card->id.']', 'app.payment.complete');

                $transactionDB = TransactionsModel::getDb()->beginTransaction();
                    $transaction = new TransactionsModel();
                    $transaction->create($input->invoiceId, $input->amount, $input->accountId, $card->id);
                    $transaction->cloud_transaction_id = $input->transactionId;
                    $transaction->state = 1;
                    $transaction->save();

                    // TODO подумать как сделать по другому
                    // для случаев, когда привязываем карту нет заказа
                    $order = OrdersModel::findOne($input->invoiceId);
                    if ($order) {
                        $order->state = 1;
                        $order->save();

                        if ($order->promo_id != 0) {
                            PromoHistoryModel::create($order->id, $order->user_id, $order->promo_id);
                        }
                    }

                $transactionDB->commit();

                Yii::info('Успешное проведение платежа ['.$transaction->id.']', 'app.payment.complete');
                return ['code' => 0];
            } catch (Exception $e) {
                $transactionDB->rollBack();
                Yii::error($e->getMessage(), 'app.payment.complete');
            }
        }

        Yii::error('Ошибка проведения платежа', 'app.payment.complete');
        return ['code' => -1];
    }

    /**
     * Callback от cloudpayment о неуспешной оплате
     * @return array
     */
    public function actionFail() {
        if (Yii::$app->getRequest()->getMethod() == 'POST') {

            Yii::info('Cloudpayment answer ['.json_encode($_POST).']', 'app.payment.fail');
            Yii::info('Cloudpayment answer fail ['.json_encode($_POST).']', 'app.sendMail');

            $input = InputPayAnswer::collect();
            try {

                $card = CardsModel::createCardIFNoExist($input);
                Yii::info('Используется карта ['.$card->id.']', 'app.payment.fail');

                $transaction = new TransactionsModel();
                $transaction->create($input->invoiceId, $input->amount, $input->accountId, $card->id);
                $transaction->cloud_transaction_id = $input->transactionId;
                $transaction->state = -1;
                $transaction->save();

                Yii::info('Успешный неудачный платеж ['.$transaction->id.']', 'app.payment.fail');
                return ['code' => 0];
            } catch (Exception $e) {
                Yii::error($e->getMessage(), 'app.payment.fail');
            }
        }

        Yii::error('Ошибка проведения неудачного платежа', 'app.payment.fail');
        return ['code' => -1];
    }
}