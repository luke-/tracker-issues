<?php

namespace tracker\controllers;

use tracker\enum\IssuePriorityEnum;
use tracker\enum\IssueStatusEnum;
use tracker\enum\IssueVisibilityEnum;

/**
 * @author Evgeniy Tkachenko <et.coder@gmail.com>
 */
class IssueRequest extends \yii\base\Model
{
    public $id;
    public $title;
    public $description;
    public $deadlineDate;
    public $deadlineTime;
    public $assignedUsers = [];
    public $tags = [];
    public $status = IssueStatusEnum::TYPE_DRAFT;
    public $visibility = IssueVisibilityEnum::TYPE_PROTECTED;
    public $priority = IssuePriorityEnum::TYPE_NORMAL;
    public $notifyAssignors = true;
    public $container;

    public function rules()
    {
        return [
            [['assignedUsers', 'tags'], 'default', 'value' => []],
            ['notifyAssignors', 'default', 'value' => true],
            ['status', 'default', 'value' => IssueStatusEnum::TYPE_DRAFT],
            ['visibility', 'default', 'value' => IssueVisibilityEnum::TYPE_PROTECTED],
            ['priority', 'default', 'value' => IssuePriorityEnum::TYPE_NORMAL],

            [['id', 'title'], 'required'],
            ['description', 'safe'],
            ['title', 'string', 'max' => 255],
            ['deadlineDate', 'date', 'format' => 'php:Y-m-d'],
            ['deadlineTime', 'time', 'format' => 'php:H:i'],
            ['status', 'in', 'range' => array_keys(IssueStatusEnum::getList())],
            ['visibility', 'in', 'range' => array_keys(IssueVisibilityEnum::getList())],
            ['priority', 'in', 'range' => array_keys(IssuePriorityEnum::getList())],
            ['assignedUsers', 'safe'],
            ['notifyAssignors', 'boolean'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'title' => \Yii::t('TrackerIssuesModule.views', 'Title'),
            'description' => \Yii::t('TrackerIssuesModule.views', 'Description'),
            'deadline' => \Yii::t('TrackerIssuesModule.views', 'Deadline'),
            'deadlineDate' => \Yii::t('TrackerIssuesModule.views', 'Deadline Date'),
            'deadlineTime' => \Yii::t('TrackerIssuesModule.views', 'Deadline Time'),
            'status' => \Yii::t('TrackerIssuesModule.views', 'Status'),
            'assignedUsers' => \Yii::t('TrackerIssuesModule.views', 'Assigned Users'),
            'visibility' => \Yii::t('TrackerIssuesModule.views', 'Visibility'),
            'priority' => \Yii::t('TrackerIssuesModule.views', 'Priority'),
            'notifyAssignors' => \Yii::t('TrackerIssuesModule.views', 'Notify assignors'),
            'tags' => \Yii::t('TrackerIssuesModule.views', 'Tags'),
        ];
    }
}
