<?php

namespace tracker\controllers;

use humhub\components\access\ControllerAccess;
use humhub\components\Controller;
use tracker\controllers\services\DocumentCreator;
use tracker\models\Document;
use tracker\models\DocumentReceiver;
use tracker\models\DocumentSearch;
use tracker\Module;
use tracker\permissions\AddDocument;
use tracker\permissions\AddReceiversToDocument;
use Yii;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;

/**
 * DocumentController implements the actions for Document model.
 */
class DocumentController extends Controller
{
    /**
     * @inheritdoc
     */
    public $subLayout = '@tracker/views/layouts/sub_layout_issues';

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
            'acl' => [
                'class' => \humhub\components\behaviors\AccessControl::className(),
                'rules' => [
                    [ControllerAccess::RULE_LOGGED_IN_ONLY],
                ],
            ],
        ];
    }

    /**
     * Lists all Document models.
     *
     * @return mixed
     */
    public function actionIndex()
    {
        $searchModel = new DocumentSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams, Yii::$app->user->identity);

        return $this->render('/document/index', [
            'dataProvider' => $dataProvider,
            'searchModel' => $searchModel,
        ]);
    }

    /**
     * Displays a single Document model.
     *
     * @param integer $id
     *
     * @return mixed
     */
    public function actionView($id)
    {
        return $this->render('view', [
            'model' => $this->findModelForUser($id, Yii::$app->user->identity),
        ]);
    }

    /**
     * Creates a new Document model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     *
     * @return mixed
     * @throws NotFoundHttpException
     */
    public function actionCreate()
    {
        if (!\Yii::$app->user->can(new AddDocument())) {
            $this->forbidden();
        }

        $documentCreator = new DocumentCreator();
        $request = \Yii::$app->request;

        if ($documentCreator->load($request->post()) && $document = $documentCreator->create()) {
            return $this->redirect(['view', 'id' => $document->id]);
        }

        return $this->renderAjax('create', [
            'documentRequest' => $documentCreator->getDocumentForm(),
        ]);
    }

    public function actionDownload($id)
    {
        $userComponent = \Yii::$app->user;
        $document = $this->findModelForUser($id, $userComponent->identity);

        /** @var Module $module */
        $module = \Yii::$app->getModule(Module::getIdentifier());
        $category = isset(Document::categories()[$document->category]) ? $document->category : 'no-category';
        $path = $module->documentRootPath . $category . '/' . $document->id . '/';

        $receiver = DocumentReceiver::findOne([
            'view_mark' => 0,
            'user_id' => $userComponent->id,
            'document_id' => $document->id,
        ]);
        if ($receiver !== null) {
            $receiver->viewed_at = date('Y-m-d H:i');
            $receiver->view_mark = 1;
            if (!$receiver->save(true, ['viewed_at', 'view_mark'])) {
                \Yii::warning(json_encode($receiver->errors));
            }
        }

        return Yii::$app
            ->response
            ->sendContentAsFile(
                file_get_contents($path . $document->file->filename),
                $document->file->filename,
                ['inline' => true,]
            );
    }

    public function actionChangeCategory($id)
    {

    }

    public function actionAddFile($id)
    {
        $document = Document::find()->byId($id)->one();

        if ($document === null) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        if ((int)$document->created_by !== (int)Yii::$app->user->id) {
            $this->forbidden();
        }

        $documentCreator = new DocumentCreator();

        if (Yii::$app->request->isPost) {
            if ($documentCreator->load(Yii::$app->request->post())) {
                if ($_document = $documentCreator->addFileToDocument($document)) {
                    return $this->redirect(['view', 'id' => $_document->id]);
                }
            }
        }

        return $this->renderAjax('form_add_file', [
            'requestModel' => $documentCreator->getDocumentForm(),
            'actionUrl' => \yii\helpers\Url::to([
                '/' . Module::getIdentifier() . '/document/add-file',
                'id' => $document->id,
            ]),
        ]);
    }

    public function actionToAddReceivers($id)
    {
        $document = Document::find()->byId($id)->one();

        if ($document === null) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        if (!\Yii::$app->user->can(new AddReceiversToDocument()) &&
            $document->created_by !== Yii::$app->user->id) {
            $this->forbidden();
        }

        $documentCreator = new DocumentCreator();

        if (Yii::$app->request->isPost) {
            if ($documentCreator->load(Yii::$app->request->post())) {
                $document = $documentCreator->addReceiversTo($document);
                return $this->redirect(['view', 'id' => $document->id]);
            }
        }

        return $this->renderAjax('to_add_receivers_document', [
            'requestModel' => $documentCreator->getDocumentForm(),
            'actionUrl' => \yii\helpers\Url::to([
                '/' . Module::getIdentifier() . '/document/to-add-receivers',
                'id' => $document->id,
            ]),
        ]);
    }

    /**
     * Finds the Document model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     *
     * @param integer $id
     * @param \yii\web\IdentityInterface $user
     *
     * @return Document the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModelForUser($id, \yii\web\IdentityInterface $user)
    {
        $model = Document::find()->readable($user)->byId($id)->one();

        if ($model === null) {

            $model = Document::find()->byCreator($user)->byId($id)->one();

            if ($model === null) {
                throw new NotFoundHttpException('The requested page does not exist.');
            }
        }

        return $model;
    }
}
