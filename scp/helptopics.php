<?php
/*********************************************************************
    helptopics.php

    Help Topics.

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require('admin.inc.php');
include_once(INCLUDE_DIR.'class.topic.php');
include_once(INCLUDE_DIR.'class.faq.php');
require_once(INCLUDE_DIR.'class.dynamic_forms.php');

$topic=null;
if($_REQUEST['id'] && !($topic=Topic::lookup($_REQUEST['id'])))
    $errors['err']=sprintf(__('%s: Unknown or invalid ID.'), __('help topic'));

if($_POST){
    switch(strtolower($_POST['do'])){
        case 'update':
            if(!$topic){
                $errors['err']=sprintf(__('%s: Unknown or invalid'), __('help topic'));
            }elseif($topic->update($_POST,$errors)){
                $msg=sprintf(__('Successfully updated %s.'),
                    __('this help topic'));
            }elseif(!$errors['err']){
                $errors['err'] = sprintf('%s %s',
                    sprintf(__('Unable to update %s.'), __('this help topic')),
                    __('Correct any errors below and try again.'));
            }
            break;
        case 'create':
            $_topic = Topic::create();
            if ($_topic->update($_POST, $errors)) {
                $topic = $_topic;
                $msg=sprintf(__('Successfully added %s.'), Format::htmlchars($_POST['topic']));
                $_REQUEST['a']=null;
            }elseif(!$errors['err']){
                $errors['err']=sprintf('%s %s',
                    sprintf(__('Unable to add %s.'), __('this help topic')),
                    __('Correct any errors below and try again.'));
            }
            break;
        case 'mass_process':
            switch(strtolower($_POST['a'])) {
            case 'sort':
                // Pass
                break;
            default:
                if(!$_POST['ids'] || !is_array($_POST['ids']) || !count($_POST['ids']))
                    $errors['err'] = sprintf(__('You must select at least %s.'),
                        __('one help topic'));
            }
            if (!$errors) {
                $count=count($_POST['ids']);

                switch(strtolower($_POST['a'])) {
                    case 'enable':
                        $topics = Topic::objects()->filter(array(
                          'topic_id__in'=>$_POST['ids'],
                        ));
                        foreach ($topics as $t) {
                          $t->setFlag(Topic::FLAG_ARCHIVED, false);
                          $t->setFlag(Topic::FLAG_ACTIVE, true);
                          $filter_actions = FilterAction::objects()->filter(array('type' => 'topic', 'configuration' => '{"topic_id":'. $t->getId().'}'));
                          FilterAction::setFilterFlag($filter_actions, 'topic', false);
                          if($t->save())
                            $num++;
                        }

                        if ($num > 0) {
                            if($num==$count)
                                $msg = sprintf(__('Successfully enabled %s'),
                                    _N('selected help topic', 'selected help topics', $count));
                            else
                                $warn = sprintf(__('%1$d of %2$d %3$s enabled'), $num, $count,
                                    _N('selected help topic', 'selected help topics', $count));
                        } else {
                            $errors['err'] = sprintf(__('Unable to enable %s'),
                                _N('selected help topic', 'selected help topics', $count));
                        }
                        break;
                    case 'disable':
                        $topics = Topic::objects()->filter(array(
                          'topic_id__in'=>$_POST['ids'],
                        ))->exclude(array(
                            'topic_id'=>$cfg->getDefaultTopicId()
                        ));
                        foreach ($topics as $t) {
                          $t->setFlag(Topic::FLAG_ARCHIVED, false);
                          $t->setFlag(Topic::FLAG_ACTIVE, false);
                          $filter_actions = FilterAction::objects()->filter(array('type' => 'topic', 'configuration' => '{"topic_id":'. $t->getId().'}'));
                          FilterAction::setFilterFlag($filter_actions, 'topic', true);
                          if($t->save())
                            $num++;
                        }
                        if ($num > 0) {
                            if($num==$count)
                                $msg = sprintf(__('Successfully disabled %s'),
                                    _N('selected help topic', 'selected help topics', $count));
                            else
                                $warn = sprintf(__('%1$d of %2$d %3$s disabled'), $num, $count,
                                    _N('selected help topic', 'selected help topics', $count));
                        } else {
                            $errors['err'] = sprintf(__('Unable to disable %s'),
                                _N('selected help topic', 'selected help topics', $count));
                        }
                        break;
                    case 'archive':
                        $topics = Topic::objects()->filter(array(
                          'topic_id__in'=>$_POST['ids'],
                        ))->exclude(array(
                            'topic_id'=>$cfg->getDefaultTopicId()
                        ));
                        foreach ($topics as $t) {
                          $t->setFlag(Topic::FLAG_ARCHIVED, true);
                          $t->setFlag(Topic::FLAG_ACTIVE, false);
                          $filter_actions = FilterAction::objects()->filter(array('type' => 'topic', 'configuration' => '{"topic_id":'. $t->getId().'}'));
                          FilterAction::setFilterFlag($filter_actions, 'topic', true);
                          if($t->save())
                            $num++;
                        }
                        if ($num > 0) {
                            if($num==$count)
                                $msg = sprintf(__('Successfully archived %s'),
                                    _N('selected help topic', 'selected help topics', $count));
                            else
                                $warn = sprintf(__('%1$d of %2$d %3$s archived'), $num, $count,
                                    _N('selected help topic', 'selected help topics', $count));
                        } else {
                            $errors['err'] = sprintf(__('Unable to archive %s'),
                                _N('selected help topic', 'selected help topics', $count));
                        }
                        break;
                    case 'delete':
					$h = TopicOrganizationModel::objects()->filter(array(
                            'topic_id__in'=>$_POST['ids']
                        ))->delete();
                        $topics = Topic::objects()->filter(array(
                            'topic_id__in'=>$_POST['ids']
                        ));

                        foreach ($topics as $t)
                            $t->delete();

                        if($topics && $topics==$count)
                            $msg = sprintf(__('Successfully deleted %s.'),
                                _N('selected help topic', 'selected help topics', $count));
                        elseif($topics>0)
                            $warn = sprintf(__('%1$d of %2$d %3$s deleted'), $topics, $count,
                                _N('selected help topic', 'selected help topics', $count));
                        elseif(!$errors['err'])
                            $errors['err']  = sprintf(__('Unable to delete %s.'),
                                _N('selected help topic', 'selected help topics', $count));

                        break;
                    case 'sort':
                        try {
                            $cfg->setTopicSortMode($_POST['help_topic_sort_mode']);
                            if ($cfg->getTopicSortMode() == 'm') {
                                foreach ($_POST as $k=>$v) {
                                    if (strpos($k, 'sort-') === 0
                                            && is_numeric($v)
                                            && ($t = Topic::lookup(substr($k, 5))))
                                        $t->setSortOrder($v);
                                }
                            }
                            $msg = __('Successfully set sorting configuration');
                        }
                        catch (Exception $ex) {
                            $errors['err'] = __('Unable to set sorting mode');
                        }
                        break;
                    default:
                        $errors['err']=sprintf('%s - %s', __('Unknown action'), __('Get technical help!'));
                }
            }
            break;
        default:
            $errors['err']=__('Unknown action');
            break;
    }
    if ($id or $topic) {
        if (!$id) $id=$topic->getId();
    }
}

$page='helptopics.inc.php';
$tip_namespace = 'manage.helptopic';
if($topic || ($_REQUEST['a'] && !strcasecmp($_REQUEST['a'],'add')))
{
    if ($topic && ($dept=$topic->getDept()) && !$dept->isActive())
      $warn = sprintf(__('%s is assigned a %s that is not active.'), __('Help Topic'), __('Department'));

    $page='helptopic.inc.php';
}

$nav->setTabActive('manage');
$ost->addExtraHeader('<meta name="tip-namespace" content="' . $tip_namespace . '" />',
    "$('#content').data('tipNamespace', '".$tip_namespace."');");
require(STAFFINC_DIR.'header.inc.php');
require(STAFFINC_DIR.$page);
include(STAFFINC_DIR.'footer.inc.php');
?>
