<?php

namespace tracker\controllers;

use humhub\modules\content\components\ContentContainerController;
use tracker\models\Assignee;
use tracker\models\Issue;
use tracker\models\IssueSearch;
use yii\web\NotFoundHttpException;

/**
 * @author Evgeniy Tkachenko <et.coder@gmail.com>
 */
class IssueController extends ContentContainerController
{
    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'stream' => [
                'class' => StreamAction::className(),
                'contentContainer' => $this->contentContainer,
            ],
            'dashboard-stream' => [
                'class' => DashboardStreamAction::className(),
            ],
        ];
    }

    public $hideSidebar = true;

    public function actionShow()
    {
        $searchModel = new IssueSearch();

        return $this->render('show', [
            'dataProvider' => $searchModel->search(\Yii::$app->request->get(), $this->contentContainer),
            'searchModel' => $searchModel,
            'contentContainer' => $this->contentContainer,
            'canCreateNewIssue' => $this->canUserDo(new \tracker\permissions\CreateIssue()),
        ]);
    }

    public function actionCreate()
    {
        if (!$this->canUserDo(new \tracker\permissions\CreateIssue())) {
            $this->forbidden();
        }

        $issueCreator = new IssueCreator();
        $request = \Yii::$app->request;

        if ($issueCreator->load($request->post()) && $issue = $issueCreator->create()) {
            return $this->redirect($issue->content->getUrl());
        }

        $issueCreator->createDraft($this->contentContainer);

        $form = $issueCreator->getIssueForm();
        $form->status = \tracker\enum\IssueStatusEnum::TYPE_WORK;
        if ($this->contentContainer instanceof \humhub\modules\user\models\User) {
            $form->visibility = \tracker\enum\IssueVisibilityEnum::TYPE_PRIVATE;
        }

        return $this->renderAjax('create', ['issueForm' => $form]);
    }

    public function actionEdit($id)
    {
        /** @var Issue|null $issue */
        $issue = Issue::find()
            ->contentContainer($this->contentContainer)
            ->readable()
            ->where([Issue::tableName() . '.id' => $id,])
            ->one();

        if ($issue === null) {
            throw new NotFoundHttpException('Issue not founded.');
        }

        if (!$this->canUserDo(new \tracker\permissions\EditIssue())) {
            $this->forbidden();
        }

        $issueEditor = new IssueEditor($issue);

        if ($issueEditor->load(\Yii::$app->request->post())) {
            if ($issue = $issueEditor->save()) {
                return $this->redirect($issue->content->getUrl());
            }
        }

        return $this->renderAjax('edit', ['issueForm' => $issueEditor->getIssueForm()]);
    }

    public function actionMarkAdopted($id)
    {
        $assignee = $this->findAssignee($id);
        $assignee->view_mark = 1;
        $assignee->viewed_at = date('Y-m-d H:i');
        $assignee->save();

        return $this->renderAjaxContent($assignee->issue->getWallOut());
    }

    public function actionMarkDone($id)
    {
        $assignee = $this->findAssignee($id);
        $assignee->finish_mark = 1;
        $assignee->finished_at = date('Y-m-d H:i');
        $assignee->save();

        return $this->renderAjaxContent($assignee->issue->getWallOut());
    }

    protected function findAssignee($id)
    {
        $assignee = Assignee::findOne(['id' => $id]);

        if ($assignee === null) {
            throw new NotFoundHttpException();
        }

        if ((int)$assignee->user_id !== (int)\Yii::$app->user->id) {
            $this->forbidden();
        }

        return $assignee;
    }

    /**
     * @param $permission
     * @param array $params
     * @param bool $allowCaching
     *
     * @return bool
     */
    protected function canUserDo($permission, $params = [], $allowCaching = true)
    {
        return $this->contentContainer
            ->getPermissionManager()
            ->can($permission, $params, $allowCaching);
    }
}
