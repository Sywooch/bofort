<?php

namespace app\controllers;

use amnah\yii2\user\models\UserToken;
use app\models\CardsModel;
use app\models\NotificationsModel;
use app\models\OrdersModel;
use app\models\PhoneConfirmForm;
use app\models\TransactionsModel;
use Exception;
use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\widgets\ActiveForm;

/**
 * Default controller for User module
 */
class DefaultController extends Controller
{
    /**
     * @var \amnah\yii2\user\Module
     * @inheritdoc
     */
    public $module;

    public $layout = '@app/views/layouts/main';

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => ['index', 'confirm', 'resend', 'logout'],
                        'allow' => true,
                        'roles' => ['?', '@'],
                    ],
                    [
                        'actions' => ['account', 'profile', 'resend-change', 'cancel', 'change-card-state', 'reset', 'account-edit', 'code-confirm', 'confirm-phone', 'remove-card',
                                            'getcards', 'getnotifications', 'getbooking', 'getaccount', 'getsecurity'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                    [
                        'actions' => ['login', 'register', 'forgot', 'login-email', 'login-callback', 'reset'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * Display index - debug page, login page, or account page
     */
    public function actionIndex()
    {
        if (defined('YII_DEBUG') && YII_DEBUG) {
            $actions = $this->module->getActions();
            return $this->render('index', ["actions" => $actions]);
        } elseif (Yii::$app->user->isGuest) {
            return $this->redirect(["/user/login"]);
        } else {
            return $this->redirect(["/user/account"]);
        }
    }

    /**
     * Display login page
     */
    public function actionLogin()
    {
        /** @var \amnah\yii2\user\models\forms\LoginForm $model */
        $model = $this->module->model("LoginForm");

        // load post data and login
        $post = Yii::$app->request->post();
        if ($model->load($post) && $model->validate()) {
            $returnUrl = $this->performLogin($model->getUser(), $model->rememberMe);
            return $this->redirect($returnUrl);
        }

        return $this->renderAjax('login', compact("model"));
    }

    /**
     * Login/register via email
     */
    public function actionLoginEmail()
    {
        /** @var \amnah\yii2\user\models\forms\LoginEmailForm $loginEmailForm */
        $loginEmailForm = $this->module->model("LoginEmailForm");

        // load post data and validate
        $post = Yii::$app->request->post();
        if ($loginEmailForm->load($post) && $loginEmailForm->sendEmail()) {
            $user = $loginEmailForm->getUser();
            $message = $user ? "Login link sent" : "Registration link sent";
            $message .= " - Please check your email";
            Yii::$app->session->setFlash("Login-success", Yii::t("user", $message));
        }

        return $this->render("loginEmail", compact("loginEmailForm"));
    }

    /**
     * Login/register callback via email
     */
    public function actionLoginCallback($token)
    {
        /** @var \amnah\yii2\user\models\User $user */
        /** @var \amnah\yii2\user\models\Profile $profile */
        /** @var \amnah\yii2\user\models\Role $role */
        /** @var \amnah\yii2\user\models\UserToken $userToken */

        $user = $this->module->model("User");
        $profile = $this->module->model("Profile");
        $userToken = $this->module->model("UserToken");

        // check token and log user in directly
        $userToken = $userToken::findByToken($token, $userToken::TYPE_EMAIL_LOGIN);
        if ($userToken && $userToken->user) {
            $returnUrl = $this->performLogin($userToken->user, $userToken->data);
            $userToken->delete();
            return $this->redirect($returnUrl);
        }

        // load post data
        $post = Yii::$app->request->post();
        $userLoaded = $user->load($post);
        $profileLoaded = $profile->load($post);
        if ($userToken && ($userLoaded || $profileLoaded)) {

            // ensure that email is taken from the $userToken (and not from user input)
            $user->email = $userToken->data;

            // validate and register
            if ($user->validate() && $profile->validate()) {
                $role = $this->module->model("Role");
                $user->setRegisterAttributes($role::ROLE_USER, $user::STATUS_ACTIVE)->save();
                $profile->setUser($user->id)->save();

                // log user in and delete token
                $returnUrl = $this->performLogin($user);
                $userToken->delete();
                return $this->redirect($returnUrl);
            }
        }

        $user->email = $userToken ? $userToken->data : null;
        return $this->render("loginCallback", compact("user", "profile", "userToken"));
    }

    /**
     * Perform the login
     */
    protected function performLogin($user, $rememberMe = true)
    {
        // log user in
        $loginDuration = $rememberMe ? $this->module->loginDuration : 0;
        Yii::$app->user->login($user, $loginDuration);

        // check for a valid returnUrl (to prevent a weird login bug)
        //   https://github.com/amnah/yii2-user/issues/115
        $loginRedirect = $this->module->loginRedirect;
        $returnUrl = Yii::$app->user->getReturnUrl($loginRedirect);
        if (strpos($returnUrl, "user/login") !== false || strpos($returnUrl, "user/logout") !== false) {
            $returnUrl = null;
        }

        return $returnUrl;
    }

    /**
     * Log user out and redirect
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        // handle redirect
        $logoutRedirect = $this->module->logoutRedirect;
        if ($logoutRedirect) {
            return $this->redirect($logoutRedirect);
        }
        return $this->goHome();
    }

    /**
     * Display registration page
     */
    public function actionRegister()
    {
        /** @var \amnah\yii2\user\models\User $user */
        /** @var \amnah\yii2\user\models\Profile $profile */
        /** @var \amnah\yii2\user\models\Role $role */

        // set up new user/profile objects
        $user = $this->module->model("User", ["scenario" => "register"]);
        $profile = $this->module->model("Profile");

        // load post data
        $post = Yii::$app->request->post();
        Yii::info('Попытка регистрации ['.json_encode($post).']', 'app.default.register');
        if ($user->load($post)) {

            // ensure profile data gets loaded
            $profile->load($post);

            // validate for ajax request
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = Response::FORMAT_JSON;
                return ActiveForm::validate($user, $profile);
            }

            // validate for normal request
            if ($user->validate() && $profile->validate()) {

                // perform registration
                $role = $this->module->model("Role");
                $user->setRegisterAttributes($role::ROLE_USER)->save();
                $profile->setUser($user->id)->save();
                $this->afterRegister($user);

                // set flash
                // don't use $this->refresh() because user may automatically be logged in and get 403 forbidden
                $successText = Yii::t("user", "Successfully registered [ {displayName} ]", ["displayName" => $user->getDisplayName()]);
                $guestText = "";
                if (Yii::$app->user->isGuest) {
                    $guestText = Yii::t("user", " - Please check your email to confirm your account");
                }
                Yii::$app->session->setFlash("Register-success", $user->username);
                Yii::info("Пользователь $user->id успешно зарегестрирован", 'app.default.register');
            }
        }

        return $this->redirect('/');
    }

    /**
     * Process data after registration
     * @param \amnah\yii2\user\models\User $user
     */
    protected function afterRegister($user)
    {
        /** @var \amnah\yii2\user\models\UserToken $userToken */
        $userToken = $this->module->model("UserToken");
        //Yii::$app->user->login($user, $this->module->loginDuration);

         //determine userToken type to see if we need to send email
        $userTokenType = null;
        if ($user->status == $user::STATUS_INACTIVE) {
            $userTokenType = $userToken::TYPE_EMAIL_ACTIVATE;
        } elseif ($user->status == $user::STATUS_UNCONFIRMED_EMAIL) {
            $userTokenType = $userToken::TYPE_EMAIL_CHANGE;
        }

        // check if we have a userToken type to process, or just log user in directly
        if ($userTokenType) {
            $userToken = $userToken::generate($user->id, $userTokenType);
            if (!$numSent = $user->sendEmailConfirmation($userToken)) {

                // handle email error
                //Yii::$app->session->setFlash("Email-error", "Failed to send email");
            }
        } else {
            Yii::$app->user->login($user, $this->module->loginDuration);
        }
    }

    /**
     * Confirm email
     */
    public function actionConfirm($token)
    {
        /** @var \amnah\yii2\user\models\UserToken $userToken */
        /** @var \amnah\yii2\user\models\User $user */

        // search for userToken
        $success = false;
        $email = "";
        $userToken = $this->module->model("UserToken");
        $userToken = $userToken::findByToken($token, [$userToken::TYPE_EMAIL_ACTIVATE, $userToken::TYPE_EMAIL_CHANGE]);
        if ($userToken) {

            // find user and ensure that another user doesn't have that email
            //   for example, user registered another account before confirming change of email
            $user = $this->module->model("User");
            $user = $user::findOne($userToken->user_id);
            $newEmail = $userToken->data;
            if ($user->confirm($newEmail)) {
                $success = true;
            }

            // set email and delete token
            $email = $newEmail ?: $user->email;
            $userToken->delete();

            Yii::$app->user->login($user, $this->module->loginDuration);
        }

        return $this->render("confirm", compact("userToken", "success", "email"));
    }

    /**
     * Account
     */
    public function actionAccount()
    {
        /** @var \amnah\yii2\user\models\User $user */
        /** @var \amnah\yii2\user\models\UserToken $userToken */

        // set up user and load post data
        $user = Yii::$app->user->identity;
        $user->setScenario("account");
        $loadedPost = $user->load(Yii::$app->request->post());

        // validate for ajax request
        if ($loadedPost && Yii::$app->request->isAjax) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ActiveForm::validate($user);
        }

        // validate for normal request
        $userToken = $this->module->model("UserToken");
        if ($loadedPost && $user->validate()) {

            // check if user changed his email
            $newEmail = $user->checkEmailChange();
            if ($newEmail) {
                $userToken = $userToken::generate($user->id, $userToken::TYPE_EMAIL_CHANGE, $newEmail);
                if (!$numSent = $user->sendEmailConfirmation($userToken)) {

                    // handle email error
                    //Yii::$app->session->setFlash("Email-error", "Failed to send email");
                }
            }

            // save, set flash, and refresh page
            $user->save(false);
            Yii::$app->session->setFlash("Account-success", Yii::t("user", "Account updated"));
            return $this->refresh();
        } else {
            $userToken = $userToken::findByUser($user->id, $userToken::TYPE_EMAIL_CHANGE);
        }

        return $this->render("account", compact("user", "userToken"));
    }

    /**
     * Profile
     */
    public function actionProfile()
    {
        /** @var \amnah\yii2\user\models\Profile $profile */

        // set up profile and load post data
        $user = Yii::$app->user->identity;
        $profile = Yii::$app->user->identity->profile;
        $notifications = NotificationsModel::find()->where(['user_id' => $user->getId()])->all();
        $new_notifications = NotificationsModel::find()->where(['user_id' => $user->getId(), 'is_open' => 0])->count();

        return $this->render("profile", compact("profile", "user", "notifications", 'new_notifications'));
    }

    public function actionGetcards() {
        $user = Yii::$app->user->identity;
        $cards = CardsModel::find()->where(['user_id' => $user->getId()])->andWhere(['!=', 'state', 2])->all();
        $transactions = TransactionsModel::find()->where(['user_id' => $user->getId()])->andWhere(['IS NOT', 'card_id', null])->all();
        return $this->renderPartial('_cards', compact('cards', 'transactions'));
    }

    public function actionGetnotifications() {
        $user = Yii::$app->user->identity;
        $notifications = NotificationsModel::find()->where(['user_id' => $user->getId()])->all();
        return $this->renderPartial('_notifications', compact('notifications'));
    }

    public function actionGetbooking() {
        $user = Yii::$app->user->identity;
        $orders = OrdersModel::find()->where(['user_id' => $user->getId()])->all();
        return $this->renderPartial('_booking', compact('orders'));
    }

    public function actionGetaccount() {
        $user = Yii::$app->user->identity;
        return $this->renderPartial('_account', compact('user'));
    }

    public function actionGetsecurity() {
        $user = Yii::$app->user->identity;
        return $this->renderPartial('_security', compact('user'));
    }

    /**
     * Resend email confirmation
     */
    public function actionResend()
    {
        /** @var \amnah\yii2\user\models\forms\ResendForm $model */

        // load post data and send email
        $model = $this->module->model("ResendForm");
        if ($model->load(Yii::$app->request->post()) && $model->sendEmail()) {

            // set flash (which will show on the current page)
            Yii::$app->session->setFlash("Resend-success", Yii::t("user", "Confirmation email resent"));
        }

        return $this->render("resend", compact("model"));
    }

    /**
     * Resend email change confirmation
     */
    public function actionResendChange()
    {
        /** @var \amnah\yii2\user\models\User $user */
        /** @var \amnah\yii2\user\models\UserToken $userToken */

        // find userToken of type email change
        $user = Yii::$app->user->identity;
        $userToken = $this->module->model("UserToken");
        $userToken = $userToken::findByUser($user->id, $userToken::TYPE_EMAIL_CHANGE);
        if ($userToken) {

            // send email and set flash message
            $user->sendEmailConfirmation($userToken);
            Yii::$app->session->setFlash("Resend-success", Yii::t("user", "Confirmation email resent"));
        }

        return $this->redirect(["/user/account"]);
    }

    /**
     * Cancel email change
     */
    public function actionCancel()
    {
        /** @var \amnah\yii2\user\models\User $user */
        /** @var \amnah\yii2\user\models\UserToken $userToken */

        // find userToken of type email change
        $user = Yii::$app->user->identity;
        $userToken = $this->module->model("UserToken");
        $userToken = $userToken::findByUser($user->id, $userToken::TYPE_EMAIL_CHANGE);
        if ($userToken) {
            $userToken->delete();
            Yii::$app->session->setFlash("Cancel-success", Yii::t("user", "Email change cancelled"));
        }

        return $this->redirect(["/user/account"]);
    }

    /**
     * Forgot password
     */
    public function actionForgot()
    {
        /** @var \amnah\yii2\user\models\forms\ForgotForm $model */

        // load post data and send email
        $model = $this->module->model("ForgotForm");
        if ($model->load(Yii::$app->request->post()) && $model->sendForgotEmail()) {

            // set flash (which will show on the current page)
            Yii::$app->session->setFlash("Forgot-success", 'Вам были высланы инструкции по смене пароля на почту.');
        }

        return $this->render("forgot", compact("model"));
    }

//    /**
//     * Reset password
//     */
//    public function actionReset()
//    {
//        /** @var \amnah\yii2\user\models\User $user */
//        /** @var \amnah\yii2\user\models\UserToken $userToken */
//
//
//        // get user and set "reset" scenario
//        $success = false;
//        $user =  Yii::$app->user->identity;
//        $user->setScenario("reset");
//
//        // load post data and reset user password
//        if ($user->load(Yii::$app->request->post()) && $user->save()) {
//
//            // delete userToken and set success = true
//
//            $success = true;
//        }
//
//        return $this->redirect(['/user/profile']);
//    }

    /**
     * Reset password
     */
    public function actionReset($token)
    {
        /** @var \amnah\yii2\user\models\User $user */
        /** @var \amnah\yii2\user\models\UserToken $userToken */

        // get user token and check expiration
        $userToken = $this->module->model("UserToken");
        $userToken = $userToken::findByToken($token, $userToken::TYPE_PASSWORD_RESET);
        if (!$userToken) {
            return $this->render('reset', ["invalidToken" => true]);
        }

        // get user and set "reset" scenario
        $success = false;
        $user = $this->module->model("User");
        $user = $user::findOne($userToken->user_id);
        $user->setScenario("reset");

        // load post data and reset user password
        if ($user->load(Yii::$app->request->post()) && $user->save()) {

            // delete userToken and set success = true
            $userToken->delete();
            $success = true;
        }

        return $this->render('reset', compact("user", "success"));
    }


    public function actionChangeCardState($id, $state) {
        Yii::info("Изменение состояния карты [$id] $state", 'app.default.change-card-state');
        try {
            CardsModel::updateAll(['state' => !$state], 'user_id = '.Yii::$app->user->getId() . ' AND state != 2');
            $card = CardsModel::findOne($id);
            $card->state = $state;
            $card->save();
            return $this->actionGetcards();
        } catch (Exception $e) {
            Yii::error($e->getMessage(), 'app.default.change-card-state');
        }

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return ['result' => false];
    }

    public function actionAccountEdit() {

        try {
            $user = Yii::$app->user->identity;
            $post = Yii::$app->request->post();

            $phone = $post['User']['phone'];

            $user->username = $post['User']['username'];
            $user->email = $post['User']['email'];
            $user->save();

            if ($user->phone != $phone) {
                $accessToken = new UserToken();
                $accessToken->user_id = $user->id;
                $accessToken->type = 5;
                $accessToken->token = rand(1000, 9999);
                $accessToken->save();

                $model = new PhoneConfirmForm();
                $model->phone = $phone;

                $result = $user->sendSmsConfirmation($phone, $accessToken->token);
                if (!$result) throw new Exception('Сообщение не было отправлено.');

                return $this->renderPartial('/user/default/_phoneCode', compact('model'));
            }
        } catch (Exception $e) {
            Yii::error($e->getMessage(), 'app.default.account-edit');
        }

        return $this->redirect(["/user/profile"]);
    }

    public function actionCodeConfirm() {
        $post = Yii::$app->request->post();
        $model = new PhoneConfirmForm();

        if ($model->load($post) and $model->validate()) {
            $user = Yii::$app->user->identity;
            $user->phone = $model->phone;
            $user->save();

            $userToken = UserToken::findByUser($user->id, 5);
            $userToken->delete();

            return $this->asJson(['success' => true]);
        }
        return $this->asJson(['success' => false]);
    }

    public function actionConfirmPhone() {
        $model = new PhoneConfirmForm();

        if ($post = Yii::$app->request->post()) {
            $phone = trim(strip_tags($post['phone']));
            $user = Yii::$app->user->identity;

            $accessToken = new UserToken();
            $accessToken->user_id = $user->id;
            $accessToken->type = 5;
            $accessToken->token = rand(1000, 9999);
            $accessToken->save();

            $model->phone = $phone;

            $result = $user->sendSmsConfirmation($phone, $accessToken->token);
            if (!$result) throw new Exception('Сообщение не было отправлено.');

            return $this->asJson(['success' => true,
                'phone' => $phone
            ]);
        }

        return $this->renderPartial('/user/default/_phoneConfirm', compact('model'));
    }

    public function actionRemoveCard($id) {
        Yii::info("Удаление карты [$id]", 'app.default.remove-card');
        try {
            $card = CardsModel::findOne($id);
            $card->state = 2;
            $card->save();
            return $this->actionGetcards();
        } catch (Exception $e) {
            Yii::error($e->getMessage(), 'app.default.remove-card');
        }

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return ['result' => false];
    }
}