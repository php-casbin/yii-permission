<?php

namespace yii\permission\tests\support;

use yii\web\Controller;

class TestController extends Controller
{
    public $behaviors = [];

    public function behaviors()
    {
        return $this->behaviors;
    }

    public function actionCreatePost()
    {
        return 'create success';
    }

    public function actionUpdatePost()
    {
        return 'update success';
    }

    public function actionDeletePost()
    {
        return 'delete success';
    }

    public function actionComment()
    {
        return 'comment success';
    }
}
