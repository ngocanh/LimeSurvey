<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}
/*
 * LimeSurvey
 * Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
 * All rights reserved.
 * License: GNU/GPL License v2 or later, see LICENSE.php
 * LimeSurvey is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 *
 */

/**
 * Printable Survey Controller
 *
 * This controller shows a printable survey.
 *
 * @package        LimeSurvey
 * @subpackage    Backend
 */
class printablesurvey extends Survey_Common_Action
{
    /**
     * Show printable survey
     * @param string $lang
     */
    function index($surveyid, $lang = null)
    {
        $surveyid = sanitize_int($surveyid);
        $survey = Survey::model()->findByPk($surveyid);
        if (!Permission::model()->hasSurveyPermission($surveyid, 'surveycontent', 'read')) {
            $aData['surveyid'] = $surveyid;
            $message['title'] = gT('Access denied!');
            $message['message'] = gT('You do not have permission to access this page.');
            $message['class'] = "error";
            $this->_renderWrappedTemplate('survey', array("message"=>$message), $aData);
        } else {
            /* Remove admin css and js */
            Yii::app()->clientScript->reset();
            $aSurveyInfo = getSurveyInfo($surveyid, $lang);
            if (!($aSurveyInfo)) {
                            $this->getController()->error('Invalid survey ID');
            }
            SetSurveyLanguage($surveyid, $lang);
            $sLanguageCode = App()->language;

            $templatename = $aSurveyInfo['template'];
            $welcome = $aSurveyInfo['surveyls_welcometext'];
            $end = $aSurveyInfo['surveyls_endtext'];
            $surveyname = $aSurveyInfo['surveyls_title'];
            $surveydesc = $aSurveyInfo['surveyls_description'];
            $surveytable = $survey->responsesTableName;
            $surveyexpirydate = $aSurveyInfo['expires'];
            $surveyfaxto = $aSurveyInfo['faxto'];
            $dateformattype = $aSurveyInfo['surveyls_dateformat'];
            Yii::app()->loadHelper('surveytranslator');

            if (!is_null($surveyexpirydate)) {
                $dformat = getDateFormatData($dateformattype);
                $dformat = $dformat['phpdate'];

                $expirytimestamp = strtotime($surveyexpirydate);
                $expirytimeofday_h = date('H', $expirytimestamp);
                $expirytimeofday_m = date('i', $expirytimestamp);

                $surveyexpirydate = date($dformat, $expirytimestamp);

                if (!empty($expirytimeofday_h) || !empty($expirytimeofday_m)) {
                    $surveyexpirydate .= ' &ndash; '.$expirytimeofday_h.':'.$expirytimeofday_m;
                };
                sprintf(gT("Please submit by %s"), $surveyexpirydate);
            } else {
                $surveyexpirydate = '';
            }
            //Fix $templatename : control if print_survey.pstpl exist
            $oTemplate = Template::model()->getTemplateConfiguration($templatename);            
            if ($oTemplate->pstplPath.DIRECTORY_SEPARATOR.'print_survey.pstpl') {
            } elseif (is_file(getTemplatePath(Yii::app()->getConfig("defaulttheme")).DIRECTORY_SEPARATOR.'print_survey.pstpl')) {
                $templatename = Yii::app()->getConfig("defaulttheme");
            } else {
                $templatename = "default";
            }

            $sFullTemplatePath = $oTemplate->path;
            $sFullTemplateUrl = Template::model()->getTemplateURL($templatename)."/";
            if (!defined('PRINT_TEMPLATE_DIR')) {
                define('PRINT_TEMPLATE_DIR', $sFullTemplatePath, true);
            }
            if (!defined('PRINT_TEMPLATE_URL')) {
                define('PRINT_TEMPLATE_URL', $sFullTemplateUrl, true);
            }

            LimeExpressionManager::StartSurvey($surveyid, 'survey', null, false, LEM_PRETTY_PRINT_ALL_SYNTAX);
            LimeExpressionManager::NavigateForwards();
            Yii::app()->clientScript->reset(); // Remove all scripts
            /* Add css */
            Yii::app()->getClientScript()->registerCssFile(App()->getConfig('publicstyleurl')."/printable.css");
            if (getLanguageRTL(App()->language)) {
                $aCssFiles = isset($oTemplate->config->files->rtl->print_css->filename) ? (array) $oTemplate->config->files->rtl->print_css->filename : array();
            } else {
                $aCssFiles = isset($oTemplate->config->files->print_css->filename) ? (array) $oTemplate->config->files->print_css->filename : array();
            }

            foreach ($aCssFiles as $cssFile) {
                Yii::app()->getClientScript()->registerCssFile("{$sFullTemplateUrl}{$cssFile}");
            }

            $condition = "sid = '{$surveyid}' AND language = '{$sLanguageCode}'";
            $degresult = QuestionGroup::model()->getAllGroups($condition, array('group_order')); //xiao,
            if (!isset($surveyfaxto) || !$surveyfaxto and isset($surveyfaxnumber)) {
                $surveyfaxto = $surveyfaxnumber; //Use system fax number if none is set in survey.
            }


            $headelements = getPrintableHeader();

            //if $showsgqacode is enabled at config.php show table name for reference
            $showsgqacode = Yii::app()->getConfig("showsgqacode");
            if (isset($showsgqacode) && $showsgqacode == true) {
                $surveyname = $surveyname."<br />[".gT('Database')." ".gT('table').": $surveytable]";
            }

            /* Get the HTML tag */
            Yii::app()->loadHelper('surveytranslator');
            $lang = App()->getLanguage();
            $langDir = (getLanguageRTL(App()->getLanguage())) ? "rtl" : "ltr";
            $htmlTag = " lang='$lang' class='dir-$langDir' dir='$langDir'";
            $survey_output = array(
            'SITENAME' => Yii::app()->getConfig("sitename")
            ,'SURVEYNAME' => $surveyname
            ,'SURVEYDESCRIPTION' => $surveydesc
            ,'WELCOME' => $welcome
            ,'END' => $end
            ,'THEREAREXQUESTIONS' => 0
            ,'SUBMIT_TEXT' => gT("Submit Your Survey.")
            ,'SUBMIT_BY' => $surveyexpirydate
            ,'THANKS' => gT("Thank you for completing this survey.")
            ,'HEADELEMENTS' => $headelements
            ,'HTMLTAG' => $htmlTag
            ,'TEMPLATEURL' => PRINT_TEMPLATE_URL
            ,'FAXTO' => $surveyfaxto
            ,'PRIVACY' => ''
            ,'GROUPS' => ''
            );

            $survey_output['FAX_TO'] = '';
            if (!empty($surveyfaxto) && $surveyfaxto != '000-00000000') {
//If no fax number exists, don't display faxing information!
                $survey_output['FAX_TO'] = gT("Please fax your completed survey to:")." $surveyfaxto";
            }


            $total_questions = 0;
            $mapquestionsNumbers = Array();
            $answertext = ''; // otherwise can throw an error on line 1617

            $fieldmap = createFieldMap($survey, 'full', false, false, $sLanguageCode);

            // =========================================================
            // START doin the business:
            foreach ($degresult->readAll() as $degrow) {
                // ---------------------------------------------------
                // START doing groups
                $deqresult = Question::model()->getQuestions($surveyid, $degrow['gid'], $sLanguageCode, 0, '"I"');
                $deqrows = array(); //Create an empty array in case FetchRow does not return any rows
                foreach ($deqresult->readAll() as $deqrow) {$deqrows[] = $deqrow; } // Get table output into array

                // Perform a case insensitive natural sort on group name then question title of a multidimensional array
                usort($deqrows, 'groupOrderThenQuestionOrder');

                if ($degrow['description']) {
                    $group_desc = $degrow['description'];
                } else {
                    $group_desc = '';
                }

                $group = array(
                    'GROUPNAME' => $degrow['group_name']
                    ,'GROUPDESCRIPTION' => $group_desc
                    ,'QUESTIONS' => '' // templated formatted content if $question is appended to this at the end of processing each question.
                );

                // A group can have only hidden questions. In that case you don't want to see the group's header/description either.
                $bGroupHasVisibleQuestions = false;
                $gid = $degrow['gid'];
                //Alternate bgcolor for different groups
                if (!isset($group['ODD_EVEN']) || $group['ODD_EVEN'] == ' g-row-even') {
                    $group['ODD_EVEN'] = ' g-row-odd'; } else {
                    $group['ODD_EVEN'] = ' g-row-even';
                }

                //Loop through questions
                foreach ($deqrows as $deqrow) {
                    // - - - - - - - - - - - - - - - - - - - - - - - - - - - - -
                    // START doing questions

                    $qidattributes = QuestionAttribute::model()->getQuestionAttributes($deqrow['qid']);
                    if ($qidattributes['hidden'] == 1 && $deqrow['type'] != '*') {
                        continue;
                    }
                    $bGroupHasVisibleQuestions = true;

                    //GET ANY CONDITIONS THAT APPLY TO THIS QUESTION

                    $sExplanation = ''; //reset conditions explanation
                    $s = 0;
                    // TMSW Condition->Relevance:  show relevance instead of this whole section to create $explanation


                    $scenarioresult = Condition::model()->getScenarios($deqrow['qid']);
                    $scenarioresult = $scenarioresult->readAll();
                    //Loop through distinct scenarios, thus grouping them together.
                    foreach ($scenarioresult as $scenariorow) {
                        if ($s == 0 && count($scenarioresult) > 1) {
                            $sExplanation .= '<div class="scenario">'." -------- Scenario {$scenariorow['scenario']} --------</div>\n\n";
                        }
                        if ($s > 0) {
                            $sExplanation .= '<div class="scenario">'.' -------- '.gT("or")." Scenario {$scenariorow['scenario']} --------</div>\n\n";
                        }

                        $x = 0;

                        $conditions1 = "qid={$deqrow['qid']} AND scenario={$scenariorow['scenario']}";
                        $distinctresult = Condition::model()->getSomeConditions(array('cqid', 'method', 'cfieldname'), $conditions1, array('cqid'), array('cqid', 'method', 'cfieldname'));

                        //Loop through each condition for a particular scenario.
                        foreach ($distinctresult->readAll() as $distinctrow) {
                            $condition = "qid = '{$distinctrow['cqid']}' AND parent_qid = 0 AND language = '{$sLanguageCode}'";
                            $subresult = Question::model()->find($condition);

                            if ($x > 0) {
                                $sExplanation .= ' <em class="scenario-and-separator">'.gT('and').'</em> ';
                            }
                            if (trim($distinctrow['method']) == '') {
//If there is no method chosen assume "equals"
                                $distinctrow['method'] = '==';
                            }

                            if ($distinctrow['cqid']) {
// cqid != 0  ==> previous answer match
                                if ($distinctrow['method'] == '==') {
                                    $sExplanation .= gT("Answer was")." ";
                                } elseif ($distinctrow['method'] == '!=') {
                                    $sExplanation .= gT("Answer was NOT")." ";
                                } elseif ($distinctrow['method'] == '<') {
                                    $sExplanation .= gT("Answer was less than")." ";
                                } elseif ($distinctrow['method'] == '<=') {
                                    $sExplanation .= gT("Answer was less than or equal to")." ";
                                } elseif ($distinctrow['method'] == '>=') {
                                    $sExplanation .= gT("Answer was greater than or equal to")." ";
                                } elseif ($distinctrow['method'] == '>') {
                                    $sExplanation .= gT("Answer was greater than")." ";
                                } elseif ($distinctrow['method'] == 'RX') {
                                    $sExplanation .= gT("Answer matched (regexp)")." ";
                                } else {
                                    $sExplanation .= gT("Answer was")." ";
                                }
                            }
                            if (!$distinctrow['cqid']) {
// cqid == 0  ==> token attribute match
                                $tokenData = getTokenFieldsAndNames($surveyid);
                                preg_match('/^{TOKEN:([^}]*)}$/', $distinctrow['cfieldname'], $extractedTokenAttr);
                                $sExplanation .= "Your ".$tokenData[strtolower($extractedTokenAttr[1])]['description']." ";
                                if ($distinctrow['method'] == '==') {
                                    $sExplanation .= gT("is")." ";
                                } elseif ($distinctrow['method'] == '!=') {
                                    $sExplanation .= gT("is NOT")." ";
                                } elseif ($distinctrow['method'] == '<') {
                                    $sExplanation .= gT("is less than")." ";
                                } elseif ($distinctrow['method'] == '<=') {
                                    $sExplanation .= gT("is less than or equal to")." ";
                                } elseif ($distinctrow['method'] == '>=') {
                                    $sExplanation .= gT("is greater than or equal to")." ";
                                } elseif ($distinctrow['method'] == '>') {
                                    $sExplanation .= gT("is greater than")." ";
                                } elseif ($distinctrow['method'] == 'RX') {
                                    $sExplanation .= gT("is matched (regexp)")." ";
                                } else {
                                    $sExplanation .= gT("is")." ";
                                }
                                $answer_section = ' '.$distinctrow['value'].' ';
                            }

                            $conresult = Condition::model()->getConditionsQuestions($distinctrow['cqid'], $deqrow['qid'], $scenariorow['scenario'], $sLanguageCode);

                            $conditions = array();
                            foreach ($conresult->readAll() as $conrow) {
                                $value = $conrow['value'];
                                switch ($conrow['type']) {
                                    case "Y":
                                    switch ($conrow['value']) {
                                        case "Y": $conditions[] = gT("Yes"); break;
                                        case "N": $conditions[] = gT("No"); break;
                                    }
                                    break;
                                    case "G":
                                    switch ($conrow['value']) {
                                        case "M": $conditions[] = gT("Male"); break;
                                        case "F": $conditions[] = gT("Female"); break;
                                    } // switch
                                    break;
                                    case "A":
                                    case "B":
                                    case ":":
                                    case ";":
                                    case "5":
                                        $conditions[] = $conrow['value'];
                                        break;
                                    case "C":
                                    switch ($conrow['value']) {
                                        case "Y": $conditions[] = gT("Yes"); break;
                                        case "U": $conditions[] = gT("Uncertain"); break;
                                        case "N": $conditions[] = gT("No"); break;
                                    } // switch
                                    break;
                                    case "E":
                                        switch ($conrow['value']) {
                                            case "I": $conditions[] = gT("Increase"); break;
                                            case "D": $conditions[] = gT("Decrease"); break;
                                            case "S": $conditions[] = gT("Same"); break;
                                        }
                                        break;
                                    case "1":
                                        $labelIndex = preg_match("/^[^#]+#([01]{1})$/", $conrow['cfieldname']);
                                        if ($labelIndex == 0) {
// TIBO

                                            $condition = "qid='{$conrow['cqid']}' AND code='{$conrow['value']}' AND scale_id=0 AND language='{$sLanguageCode}'";
                                            $fresult = Answer::model()->getAllRecords($condition);

                                            foreach ($fresult->readAll() as $frow) {
                                                $conditions[] = $frow['answer'];
                                            } // while
                                        } elseif ($labelIndex == 1) {

                                            $condition = "qid='{$conrow['cqid']}' AND code='{$conrow['value']}' AND scale_id=1 AND language='{$sLanguageCode}'";
                                            $fresult = Answer::model()->getAllRecords($condition);
                                            foreach ($fresult->readAll() as $frow) {
                                                $conditions[] = $frow['answer'];
                                            } // while
                                        }
                                        break;
                                    case "L":
                                    case "!":
                                    case "O":
                                    case "R":
                                        $condition = "qid='{$conrow['cqid']}' AND code='{$conrow['value']}' AND language='{$sLanguageCode}'";
                                        $ansresult = Answer::model()->findAll($condition);

                                        foreach ($ansresult as $ansrow) {
                                            $conditions[] = $ansrow['answer'];
                                        }
                                        if ($conrow['value'] == "-oth-") {
                                            $conditions[] = gT("Other");
                                        }
                                        $conditions = array_unique($conditions);
                                        break;
                                    case "M":
                                    case "P":
                                        $condition = " parent_qid='{$conrow['cqid']}' AND title='{$conrow['value']}' AND language='{$sLanguageCode}'";
                                        $ansresult = Question::model()->findAll($condition);
                                        foreach ($ansresult as $ansrow) {
                                            $conditions[] = $ansrow['question'];
                                        }
                                        $conditions = array_unique($conditions);
                                        break;
                                    case "N":
                                    case "K":
                                        $conditions[] = $value;
                                        break;
                                    case "F":
                                    case "H":
                                    default:
                                        $condition = " qid='{$conrow['cqid']}' AND code='{$conrow['value']}' AND language='{$sLanguageCode}'";
                                        $fresult = Answer::model()->getAllRecords($condition);
                                        foreach ($fresult->readAll() as $frow) {
                                            $conditions[] = $frow['answer'];
                                        } // while
                                        break;
                                } // switch

                                // Now let's complete the answer text with the answer_section
                                $answer_section = "";
                                switch ($conrow['type']) {
                                    case "A":
                                    case "B":
                                    case "C":
                                    case "E":
                                    case "F":
                                    case "H":
                                    case "K":
                                        $thiscquestion = $fieldmap[$conrow['cfieldname']];
                                        $condition = "parent_qid='{$conrow['cqid']}' AND title='{$thiscquestion['aid']}' AND language='{$sLanguageCode}'";
                                        $ansresult = Question::model()->findAll($condition);

                                        foreach ($ansresult as $ansrow) {
                                            $answer_section = " (".$ansrow['question'].")";
                                        }
                                        break;

                                    case "1": // dual: (Label 1), (Label 2)
                                        $labelIndex = substr($conrow['cfieldname'], -1);
                                        $thiscquestion = $fieldmap[$conrow['cfieldname']];
                                        $condition = "parent_qid='{$conrow['cqid']}' AND title='{$thiscquestion['aid']}' AND language='{$sLanguageCode}'";
                                        $ansresult = Question::model()->findAll($condition);
                                        $cqidattributes = QuestionAttribute::model()->getQuestionAttributes($conrow['cqid']);
                                        if ($labelIndex == 0) {
                                            if (trim($cqidattributes['dualscale_headerA'][$sLanguageCode]) != '') {
                                                $header = gT($cqidattributes['dualscale_headerA'][$sLanguageCode]);
                                            } else {
                                                $header = '1';
                                            }
                                        } elseif ($labelIndex == 1) {
                                            if (trim($cqidattributes['dualscale_headerB'][$sLanguageCode]) != '') {
                                                $header = gT($cqidattributes['dualscale_headerB'][$sLanguageCode]);
                                            } else {
                                                $header = '2';
                                            }
                                        }
                                        foreach ($ansresult as $ansrow) {
                                            $answer_section = " (".$ansrow->question." ".sprintf(gT("Label %s"), $header).")";
                                        }
                                        break;
                                    case ":":
                                    case ";": //multi flexi: ( answer [label] )
                                        $thiscquestion = $fieldmap[$conrow['cfieldname']];
                                        $condition = "parent_qid='{$conrow['cqid']}' AND title='{$thiscquestion['aid']}' AND language='{$sLanguageCode}'";
                                        $ansresult = Question::model()->findAll($condition);
                                        foreach ($ansresult as $ansrow) {

                                            $condition = "qid = '{$conrow['cqid']}' AND code = '{$conrow['value']}' AND language= '{$sLanguageCode}'";
                                            $fresult = Answer::model()->findAll($condition);
                                            foreach ($fresult as $frow) {
                                                //$conditions[]=$frow['title'];
                                                $answer_section = " (".$ansrow->question."[".$frow['answer']."])";
                                            } // while
                                        }
                                        break;
                                    case "R": // (Rank 1), (Rank 2)... TIBO
                                        $thiscquestion = $fieldmap[$conrow['cfieldname']];
                                        $rankid = $thiscquestion['aid'];
                                        $answer_section = " (".gT("RANK")." $rankid)";
                                        break;
                                    default: // nothing to add
                                        break;
                                }
                            }

                            if (count($conditions) > 1) {
                                $sExplanation .= "'".implode("' <em class='scenario-or-separator'>".gT("or")."</em> '", $conditions)."'";
                            } elseif (count($conditions) == 1) {
                                $sExplanation .= "'".$conditions[0]."'";
                            }
                            unset($conditions);
                            // Following line commented out because answer_section  was lost, but is required for some question types
                            //$explanation .= " ".gT("to question")." '".$mapquestionsNumbers[$distinctrow['cqid']]."' $answer_section ";
                            if ($distinctrow['cqid']) {
                                $sExplanation .= " <span class='scenario-at-separator'>".gT("at question")."</span> '".$mapquestionsNumbers[$distinctrow['cqid']]." [".$subresult['title']."]' (".strip_tags($subresult['question'])."$answer_section)";
                            } else {
                                $sExplanation .= " ".$distinctrow['value'];
                            }
                            //$distinctrow
                            $x++;
                        }
                        $s++;
                    }

                        $qinfo = LimeExpressionManager::GetQuestionStatus($deqrow['qid']);
                        $relevance = trim($qinfo['info']['relevance']);
                        $sEquation = $qinfo['relEqn'];

                        if (trim($relevance) != '' && trim($relevance) != '1') {
                            if (isset($qidattributes['printable_help'][$sLanguageCode]) && $qidattributes['printable_help'][$sLanguageCode] != '') {
                                $sExplanation = $qidattributes['printable_help'][$sLanguageCode];
                            } elseif ($sExplanation == '') {
// There is only a relevance equation without conditions
                                $sExplanation = $sEquation;
                                $sEquation = '&nbsp;'; // No need to show it twice
                            }
                            $sExplanation = "<div class='strong'>".gT('Only answer this question if the following conditions are met:')."</div> ".$sExplanation;
                            if (Yii::app()->getConfig('showrelevance')) {
                                $sExplanation .= "<div class='printable_equation'>".$sEquation."</div>";
                            }
                        } else {
                            $sExplanation = '';
                        }

                        ++$total_questions;

                        //TIBO map question qid to their q number
                        $mapquestionsNumbers[$deqrow['qid']] = $total_questions;
                        //END OF GETTING CONDITIONS

                        $qid = $deqrow['qid'];
                        $fieldname = "$surveyid"."X"."$gid"."X"."$qid";

                        if (isset($showsgqacode) && $showsgqacode == true) {
                            $deqrow['question'] = $deqrow['question']."<br />".gT("ID:")." $fieldname <br />".
                                                    gT("Question code:")." ".$deqrow['title'];
                        }

                        $question = array(
                            'QUESTION_NUMBER' => $total_questions    // content of the question code field
                        ,'QUESTION_CODE' => $deqrow['title']
                        ,'QUESTION_TEXT' => preg_replace('/(?:<br ?\/?>|<\/(?:p|h[1-6])>)$/is', '', $deqrow['question'])    // content of the question field
                        ,'QUESTION_SCENARIO' => $sExplanation    // if there are conditions on a question, list the conditions.
                        ,'QUESTION_MANDATORY' => ''        // translated 'mandatory' identifier
                        ,'QUESTION_ID' => $deqrow['qid']    // id to be added to wrapping question div
                        ,'QUESTION_CLASS' => Question::getQuestionClass($deqrow['type'])    // classes to be added to wrapping question div
                        ,'QUESTION_TYPE_HELP' => $qinfo['validTip']   // ''        // instructions on how to complete the question // prettyValidTip is too verbose; assuming printable surveys will use static values
                        ,'QUESTION_MAN_MESSAGE' => ''        // (not sure if this is used) mandatory error
                        ,'QUESTION_VALID_MESSAGE' => ''        // (not sure if this is used) validation error
                        ,'QUESTION_FILE_VALID_MESSAGE' => ''// (not sure if this is used) file validation error
                        ,'QUESTIONHELP' => ''            // content of the question help field.
                        ,'ANSWER' => ''                // contains formatted HTML answer
                        );
                        if (trim($question['QUESTION_TYPE_HELP']) != "") {
                            $question['QUESTION_TYPE_HELP'] = CHtml::tag("div", array("class"=>"tip-help"), $question['QUESTION_TYPE_HELP']);
                        }
                        if (isset($aQuestionAttributes['cssclass']) && $aQuestionAttributes['cssclass'] != "") {
                            $attributeClass = trim(LimeExpressionManager::ProcessString($aQuestionAttributes['cssclass'], null, array(), false, 1, 1, false, false, true));
                            $question['QUESTION_CLASS'] .= " ".Chtml::encode($attributeClass);
                        }
                        /* Add a PRINT_QUESTION_CODE : same than used in "automatic system generation (with EM condition) */
                        $question['QUESTION_PRINT_CODE'] = "{$question['QUESTION_NUMBER']} [{$question['QUESTION_CODE']}]";
                        $showqnumcode = Yii::app()->getConfig('showqnumcode');
                        if (($showqnumcode == 'choose' && ($aSurveyInfo['showqnumcode'] == 'N' || $aSurveyInfo['showqnumcode'] == 'X')) || $showqnumcode == 'number' || $showqnumcode == 'none') {
                            $question['QUESTION_CODE'] = '';
                        }
                        if (($showqnumcode == 'choose' && ($aSurveyInfo['showqnumcode'] == 'C' || $aSurveyInfo['showqnumcode'] == 'X')) || $showqnumcode == 'code' || $showqnumcode == 'none') {
                            $question['QUESTION_NUMBER'] = '';
                        }

                        if ($deqrow['mandatory'] == 'Y') {
                            $question['QUESTION_MANDATORY'] = gT('*'); // Must add a real string here !
                            $question['QUESTION_CLASS'] .= ' mandatory';
                        }


                        //DIFFERENT TYPES OF DATA FIELD HERE
                        if ($deqrow['help']) {
                            $question['QUESTIONHELP'] = $deqrow['help'];
                        }


                        if (!empty($qidattributes['page_break'])) {
                            $question['QUESTION_CLASS'] .= ' breakbefore ';
                        }


                        if (isset($qidattributes['maximum_chars']) && $qidattributes['maximum_chars'] != '') {
                            $question['QUESTION_CLASS'] = "max-chars-{$qidattributes['maximum_chars']} ".$question['QUESTION_CLASS'];
                        }

                        switch ($deqrow['type']) {
                            // ==================================================================
                            case "5":    //5 POINT CHOICE
                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT('Please choose *only one* of the following:'));
                                $question['ANSWER'] .= "\n\t<ul class='list-print-answers list-unstyled'>\n";
                                for ($i = 1; $i <= 5; $i++) {
                                    $question['ANSWER'] .= "\t\t<li>\n\t\t\t".self::_input_type_image('radio', $i)."\n\t\t\t$i ".self::_addsgqacode("($i)")."\n\t\t</li>\n";
                                }
                                $question['ANSWER'] .= "\t</ul>\n";

                                break;

                                // ==================================================================
                            case "D":  //DATE
                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT('Please enter a date:'));
                                $question['ANSWER'] .= "\t".self::_input_type_image('text', $question['QUESTION_TYPE_HELP'], 30, 1);
                                break;

                                // ==================================================================
                            case "G":  //GENDER
                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please choose *only one* of the following:"));

                                $question['ANSWER'] .= "\n\t<ul class='list-print-answers list-unstyled'>\n";
                                $question['ANSWER'] .= "\t\t<li>\n\t\t\t".self::_input_type_image('radio', gT("Female"))."\n\t\t\t".gT("Female")." ".self::_addsgqacode("(F)")."\n\t\t</li>\n";
                                $question['ANSWER'] .= "\t\t<li>\n\t\t\t".self::_input_type_image('radio', gT("Male"))."\n\t\t\t".gT("Male")." ".self::_addsgqacode("(M)")."\n\t\t</li>\n";
                                $question['ANSWER'] .= "\t</ul>\n";
                                break;

                                // ==================================================================
                            case "L": //LIST drop-down/radio-button list

                                // ==================================================================
                            case "!": //List - dropdown
                                if (isset($qidattributes['category_separator']) && trim($qidattributes['category_separator']) != '') {
                                    $optCategorySeparator = $qidattributes['category_separator'];
                                } else {
                                    unset($optCategorySeparator);
                                }

                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please choose *only one* of the following:"));
                                $question['QUESTION_TYPE_HELP'] .= self::_array_filter_help($qidattributes, $sLanguageCode, $surveyid);

                                $dearesult = Answer::model()->getAllRecords(" qid='{$deqrow['qid']}' AND language='{$sLanguageCode}' ", array('sortorder', 'answer'));
                                $dearesult = $dearesult->readAll();
                                $deacount = count($dearesult);
                                if ($deqrow['other'] == "Y") {$deacount++; }

                                $wrapper = setupColumns(0, $deacount, 'list-print-answers list-unstyled');

                                $question['ANSWER'] = $wrapper['whole-start'];

                                $rowcounter = 0;
                                $colcounter = 1;

                                foreach ($dearesult as $dearow) {
                                    if (isset($optCategorySeparator)) {
                                        list ($category, $answer) = explode($optCategorySeparator, $dearow['answer']);
                                        if ($category != '') {
                                            $dearow['answer'] = "($category) $answer ".self::_addsgqacode("(".$dearow['code'].")");
                                        } else {
                                            $dearow['answer'] = $answer.self::_addsgqacode(" (".$dearow['code'].")");
                                        }
                                        $question['ANSWER'] .= "\t".$wrapper['item-start']."\t\t".self::_input_type_image('radio', $dearow['answer'])."\n\t\t\t".$dearow['answer']."\n".$wrapper['item-end'];
                                    } else {
                                        $question['ANSWER'] .= "\t".$wrapper['item-start']."\t\t".self::_input_type_image('radio', $dearow['answer'])."\n\t\t\t".$dearow['answer'].self::_addsgqacode(" (".$dearow['code'].")")."\n".$wrapper['item-end'];
                                    }
                                    ++$rowcounter;
                                    if ($rowcounter == $wrapper['maxrows'] && $colcounter < $wrapper['cols']) {
                                        if ($colcounter == $wrapper['cols'] - 1) {
                                            $question['ANSWER'] .= $wrapper['col-devide-last'];
                                        } else {
                                            $question['ANSWER'] .= $wrapper['col-devide'];
                                        }
                                        $rowcounter = 0;
                                        ++$colcounter;
                                    }
                                }
                                if ($deqrow['other'] == 'Y') {
                                    if (trim($qidattributes["other_replace_text"][$sLanguageCode]) == '')
                                    {$qidattributes["other_replace_text"][$sLanguageCode] = gT("Other"); }
                                    //                    $printablesurveyoutput .="\t".$wrapper['item-start']."\t\t".self::_input_type_image('radio' , gT("Other"))."\n\t\t\t".gT("Other")."\n\t\t\t<input type='text' size='30' readonly='readonly' />\n".$wrapper['item-end'];
                                    $question['ANSWER'] .= $wrapper['item-start-other'].self::_input_type_image('radio', gT($qidattributes["other_replace_text"][$sLanguageCode])).' '.gT($qidattributes["other_replace_text"][$sLanguageCode]).self::_addsgqacode(" (-oth-)")."\n\t\t\t".self::_input_type_image('other').self::_addsgqacode(" (".$deqrow['sid']."X".$deqrow['gid']."X".$deqrow['qid']."other)")."\n".$wrapper['item-end'];
                                }
                                $question['ANSWER'] .= $wrapper['whole-end'];
                                //Let's break the presentation into columns.
                                break;

                                // ==================================================================
                            case "O":  //LIST WITH COMMENT
                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please choose *only one* of the following:"));
                                $dearesult = Answer::model()->getAllRecords(" qid='{$deqrow['qid']}' AND language='{$sLanguageCode}'", array('sortorder', 'answer'));

                                $question['ANSWER'] = "\t<ul class='list-print-answers list-unstyled'>\n";
                                foreach ($dearesult->readAll() as $dearow) {
                                    $question['ANSWER'] .= "\t\t<li>\n\t\t\t".self::_input_type_image('radio', $dearow['answer'])."\n\t\t\t".$dearow['answer'].self::_addsgqacode(" (".$dearow['code'].")")."\n\t\t</li>\n";
                                }
                                $question['ANSWER'] .= "\t</ul>\n";

                                $question['ANSWER'] .= "\t<div class=\"comment\">\n\t\t".gT("Make a comment on your choice here:")."\n";
                                $question['ANSWER'] .= "\t\t".self::_input_type_image('textarea', gT("Make a comment on your choice here:"), 50, 8).self::_addsgqacode(" (".$deqrow['sid']."X".$deqrow['gid']."X".$deqrow['qid']."comment)")."\n\t</div>\n";
                                break;

                                // ==================================================================
                            case "R":  //RANKING Type Question
                                $rearesult = Answer::model()->getAllRecords(" qid='{$deqrow['qid']}' AND language='{$sLanguageCode}'", array('sortorder', 'answer'));
                                $rearesult = $rearesult->readAll();
                                $reacount = count($rearesult);
                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please number each box in order of preference from 1 to")." $reacount");
                                $question['QUESTION_TYPE_HELP'] .= self::_min_max_answers_help($qidattributes, $sLanguageCode, $surveyid);
                                $question['ANSWER'] = "\n<ul class='list-print-answers list-unstyled'>\n";
                                foreach ($rearesult as $rearow) {
                                    $question['ANSWER'] .= "\t<li>\n";
                                    $question['ANSWER'] .= "\t".self::_input_type_image('rank')."\n";
                                    $question['ANSWER'] .= "\t\t".$rearow['answer'].self::_addsgqacode(" (".$fieldname.$rearow['code'].")")."\n";
                                    $question['ANSWER'] .= "\t</li>\n";
                                }
                                $question['ANSWER'] .= "\n</ul>\n";
                                break;

                                // ==================================================================
                            case "M":  //Multiple choice (Quite tricky really!)

                                if (trim($qidattributes['display_columns']) != '') {
                                    $dcols = $qidattributes['display_columns'];
                                } else {
                                    $dcols = 0;
                                }
                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please choose *all* that apply:"));
                                $question['QUESTION_TYPE_HELP'] .= self::_array_filter_help($qidattributes, $sLanguageCode, $surveyid);

                                $mearesult = Question::model()->getAllRecords(" parent_qid='{$deqrow['qid']}' AND language='{$sLanguageCode}' ", array('question_order'));
                                $mearesult = $mearesult->readAll();
                                $meacount = count($mearesult);
                                if ($deqrow['other'] == 'Y') {$meacount++; }

                                $wrapper = setupColumns($dcols, $meacount, 'list-print-answers list-unstyled');
                                $question['ANSWER'] = $wrapper['whole-start'];

                                $rowcounter = 0;
                                $colcounter = 1;

                                foreach ($mearesult as $mearow) {
                                    $question['ANSWER'] .= $wrapper['item-start'].self::_input_type_image('checkbox', $mearow['question'])."\n\t\t".$mearow['question'].self::_addsgqacode(" (".$fieldname.$mearow['title'].") ").$wrapper['item-end'];
                                    ++$rowcounter;
                                    if ($rowcounter == $wrapper['maxrows'] && $colcounter < $wrapper['cols']) {
                                        if ($colcounter == $wrapper['cols'] - 1) {
                                            $question['ANSWER'] .= $wrapper['col-devide-last'];
                                        } else {
                                            $question['ANSWER'] .= $wrapper['col-devide'];
                                        }
                                        $rowcounter = 0;
                                        ++$colcounter;
                                    }
                                }
                                if ($deqrow['other'] == "Y") {
                                    if (trim($qidattributes['other_replace_text'][$sLanguageCode]) == '') {
                                        $qidattributes["other_replace_text"][$sLanguageCode] = "Other";
                                    }
                                    if (!isset($mearow['answer'])) {
                                        $mearow['answer'] = "";
                                    }
                                    $question['ANSWER'] .= $wrapper['item-start-other'].self::_input_type_image('checkbox', $mearow['answer']).gT($qidattributes["other_replace_text"][$sLanguageCode]).":\n\t\t".self::_input_type_image('other').self::_addsgqacode(" (".$fieldname."other) ").$wrapper['item-end'];
                                }
                                $question['ANSWER'] .= $wrapper['whole-end'];
                                //                }
                                break;

                                    // ==================================================================
                            case "P":  //Multiple choice with comments
                                $aWidth = $this->getColumnWidth($qidattributes['choice_input_columns'], $qidattributes['text_input_columns']);
                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please choose all that apply and provide a comment:"));

                                $question['QUESTION_TYPE_HELP'] .= self::_array_filter_help($qidattributes, $sLanguageCode, $surveyid);
                                $mearesult = Question::model()->getAllRecords("parent_qid='{$deqrow['qid']}'  AND language='{$sLanguageCode}'", array('question_order'));
                                //                $printablesurveyoutput .="\t\t\t<u>".gT("Please choose all that apply and provide a comment:")."</u><br />\n";
                                $j = 0;
                                $longest_string = 0;
                                foreach ($mearesult->readAll() as $mearow) {
                                    $longest_string = longestString($mearow['question'], $longest_string);
                                    $question['ANSWER'] .= "\t<li class='row'>";
                                    $question['ANSWER'] .= "<div class='col-sm-{$aWidth['label']}'>\n\t\t".self::_input_type_image('checkbox', $mearow['question']).$mearow['question'].self::_addsgqacode(" (".$fieldname.$mearow['title'].") ")."</div>\n";
                                    $question['ANSWER'] .= "\t\t<div class='col-sm-{$aWidth['answer']}'>".self::_input_type_image('text', 'comment box', 50).self::_addsgqacode(" (".$fieldname.$mearow['title']."comment) ")."</div>\n";
                                    $question['ANSWER'] .= "\t</li>\n";
                                    $j++;
                                }
                                if ($deqrow['other'] == "Y") {
                                    $question['ANSWER'] .= "\t<li class=\"other\">\n\t\t<div class=\"other-replacetext\">".gT('Other:').self::_input_type_image('other', '', 1)."</div>".self::_input_type_image('othercomment', 'comment box', 50).self::_addsgqacode(" (".$fieldname."other) ")."\n\t</li>\n";
                                    $j++;
                                }

                                $question['ANSWER'] = "\n<ul class='list-print-answers list-unstyled'>\n".$question['ANSWER']."</ul>\n";
                                break;


                                // ==================================================================
                            case "Q":  //MULTIPLE SHORT TEXT
                                $aWidth = $this->getColumnWidth($qidattributes['label_input_columns'], $qidattributes['text_input_columns']);
                                break;
                            case "K":  //MULTIPLE NUMERICAL
                                //~ $question['QUESTION_TYPE_HELP'] = "";
                                $width = (isset($qidattributes['input_size']) && $qidattributes['input_size']) ? $qidattributes['input_size'] : null;
                                $height = (isset($qidattributes['display_rows']) && $qidattributes['display_rows']) ? $qidattributes['display_rows'] : null;
    //                            if (!empty($qidattributes['equals_num_value']))
    //                            {
    //                                $question['QUESTION_TYPE_HELP'] .= "* ".sprintf(gT('Total of all entries must equal %d'),$qidattributes['equals_num_value'])."<br />\n";
    //                            }
    //                            if (!empty($qidattributes['max_num_value']))
    //                            {
    //                                $question['QUESTION_TYPE_HELP'] .= sprintf(gT('Total of all entries must not exceed %d'), $qidattributes['max_num_value'])."<br />\n";
    //                            }
    //                            if (!empty($qidattributes['min_num_value']))
    //                            {
    //                                $question['QUESTION_TYPE_HELP'] .= sprintf(gT('Total of all entries must be at least %s'),$qidattributes['min_num_value'])."<br />\n";
    //                            }
                                if (!isset($aWidth)) {
                                    $aWidth = $this->getColumnWidth($qidattributes['label_input_columns'], $qidattributes['text_input_width']);
                                }

                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please write your answer(s) here:"));


                                $longest_string = 0;
                                $mearesult = Question::model()->getAllRecords("parent_qid='{$deqrow['qid']}' AND language='{$sLanguageCode}'", array('question_order'));
                                $question['ANSWER'] = "";
                                foreach ($mearesult->readAll() as $mearow) {
                                    $longest_string = longestString($mearow['question'], $longest_string);
                                    if (isset($qidattributes['slider_layout']) && $qidattributes['slider_layout'] == 1) {
                                        $mearow['question'] = explode(':', $mearow['question']);
                                        $mearow['question'] = $mearow['question'][0];
                                    }
                                    $question['ANSWER'] .= "\t<li class='row'>\n";
                                    $question['ANSWER'] .= "\t\t<div class='col-sm-{$aWidth['label']}'>".$mearow['question']."</div>\n";
                                    $question['ANSWER'] .= "\t\t<div class='col-sm-{$aWidth['answer']}'>".self::_input_type_image('text', $mearow['question'], $width, $height).self::_addsgqacode(" (".$fieldname.$mearow['title'].") ")."</div>\n";
                                    $question['ANSWER'] .= "\t</li>\n";
                                }
                                $question['ANSWER'] = "\n<ul class='list-print-answers list-unstyled'>\n".$question['ANSWER']."</ul>\n";
                                break;


                                // ==================================================================
                            case "S":  //SHORT TEXT
                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please write your answer here:"));
                                $width = (isset($qidattributes['input_size']) && $qidattributes['input_size']) ? $qidattributes['input_size'] : null;
                                $height = (isset($qidattributes['display_rows']) && $qidattributes['display_rows']) ? $qidattributes['display_rows'] : null;
                                $question['ANSWER'] = self::_input_type_image('text', $question['QUESTION_TYPE_HELP'], $width, $height);
                                break;
                                // ==================================================================
                            case "T":  //LONG TEXT
                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please write your answer here:"));
                                $width = (isset($qidattributes['input_size']) && $qidattributes['input_size']) ? $qidattributes['input_size'] : null;
                                $height = (isset($qidattributes['display_rows']) && $qidattributes['display_rows']) ? $qidattributes['display_rows'] : 5;
                                $question['ANSWER'] = self::_input_type_image('textarea', $question['QUESTION_TYPE_HELP'], $width, $height);
                                break;


                                // ==================================================================
                            case "U":  //HUGE TEXT
                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please write your answer here:"));
                                $width = (isset($qidattributes['input_size']) && $qidattributes['input_size']) ? $qidattributes['input_size'] : null;
                                $height = (isset($qidattributes['display_rows']) && $qidattributes['display_rows']) ? $qidattributes['display_rows'] : 20;
                                $question['ANSWER'] = self::_input_type_image('textarea', $question['QUESTION_TYPE_HELP'], $width, $height);
                                break;


                                // ==================================================================
                            case "N":  //NUMERICAL
                                $prefix = "";
                                $suffix = "";
                                if ($qidattributes['prefix'][$sLanguageCode] != "") {
                                    $prefix = $qidattributes['prefix'][$sLanguageCode];
                                }
                                if ($qidattributes['suffix'][$sLanguageCode] != "") {
                                    $suffix = $qidattributes['suffix'][$sLanguageCode];
                                }
                                $width = (isset($qidattributes['input_size']) && $qidattributes['input_size']) ? $qidattributes['input_size'] : null;
                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please write your answer here:"));
                                $question['ANSWER'] = "<ul class='list-print-answers list-unstyled'>\n\t<li>\n\t\t<span>$prefix</span>\n\t\t".self::_input_type_image('text', $question['QUESTION_TYPE_HELP'], $width)."\n\t\t<span>$suffix</span>\n\t\t</li>\n\t</ul>";
                                break;

                                // ==================================================================
                            case "Y":  //YES/NO
                                  $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please choose *only one* of the following:"));
                                $question['ANSWER'] = "\n<ul class='list-print-answers list-unstyled'>\n\t<li>\n\t\t".self::_input_type_image('radio', gT('Yes'))."\n\t\t".gT('Yes').self::_addsgqacode(" (Y)")."\n\t</li>\n";
                                $question['ANSWER'] .= "\n\t<li>\n\t\t".self::_input_type_image('radio', gT('No'))."\n\t\t".gT('No').self::_addsgqacode(" (N)")."\n\t</li>\n</ul>\n";
                                break;


                                // ==================================================================
                            case "A":  //ARRAY (5 POINT CHOICE)
                                $condition = "parent_qid = '{$deqrow['qid']}'  AND language= '{$sLanguageCode}'";
                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please choose the appropriate response for each item:"));
                                $question['QUESTION_TYPE_HELP'] .= self::_array_filter_help($qidattributes, $sLanguageCode, $surveyid);
                                $answerwidth = (trim($qidattributes['answer_width']) != '') ? $qidattributes['answer_width'] : 33;
                                $question['ANSWER'] .= "\n<table class='table-print-answers table table-bordered'>\n\t<thead>\n\t\t<tr>\n";
                                $question['ANSWER'] .= "\t\t\t<td style='width:{$answerwidth}%'>{NOTEMPTY}</td>\n";
                                for ($i = 1; $i <= 5; $i++) {
                                    $question['ANSWER'] .= "\t\t\t<th>$i".self::_addsgqacode(" ($i)")."</th>\n";
                                }
                                $question['ANSWER'] .= "\t</tr></thead>\n\n\t<tbody>\n";
                                $j = 0;
                                $rowclass = 'ls-odd';
                                $mearesult = Question::model()->getAllRecords($condition, array('question_order'));
                                foreach ($mearesult->readAll() as $mearow) {
                                    $question['ANSWER'] .= "\t\t<tr class=\"$rowclass\">\n";
                                    $rowclass = alternation($rowclass, 'row');

                                    //semantic differential question type?
                                    if (strpos($mearow['question'], '|')) {
                                        $answertext = substr($mearow['question'], 0, strpos($mearow['question'], '|')).self::_addsgqacode(" (".$fieldname.$mearow['title'].")")." ";
                                    } else {
                                        $answertext = $mearow['question'].self::_addsgqacode(" (".$fieldname.$mearow['title'].")");
                                    }
                                    $question['ANSWER'] .= "\t\t\t<th class=\"answertext\">$answertext</th>\n";

                                    for ($i = 1; $i <= 5; $i++) {
                                        $question['ANSWER'] .= "\t\t\t<td>".self::_input_type_image('radio', $i)."</td>\n";
                                    }

                                    $answertext .= $mearow['question'];

                                    //semantic differential question type?
                                    if (strpos($mearow['question'], '|')) {
                                        $answertext2 = substr($mearow['question'], strpos($mearow['question'], '|') + 1);
                                        $question['ANSWER'] .= "\t\t\t<th class=\"answertextright\">$answertext2</td>\n";
                                    }
                                    $question['ANSWER'] .= "\t\t</tr>\n";
                                    $j++;
                                }
                                $question['ANSWER'] .= "\t</tbody>\n</table>\n";
                                break;

                                // ==================================================================
                            case "B":  //ARRAY (10 POINT CHOICE)

                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please choose the appropriate response for each item:"));
                                $question['QUESTION_TYPE_HELP'] .= self::_array_filter_help($qidattributes, $sLanguageCode, $surveyid);
                                $answerwidth = (trim($qidattributes['answer_width']) != '') ? $qidattributes['answer_width'] : 33;
                                $question['ANSWER'] .= "\n<table class='table-print-answers table table-bordered'>\n\t<thead>\n\t\t<tr>\n";
                                $question['ANSWER'] .= "\t\t\t<td style='width:{$answerwidth}%'>{NOTEMPTY}</td>\n";
                                for ($i = 1; $i <= 10; $i++) {
                                    $question['ANSWER'] .= "\t\t\t<th>$i".self::_addsgqacode(" ($i)")."</th>\n";
                                }
                                $question['ANSWER'] .= "\t</tr></thead>\n\n\t<tbody>\n";
                                $j = 0;
                                $rowclass = 'ls-odd';
                                $mearesult = Question::model()->getAllRecords(" parent_qid='{$deqrow['qid']}' AND language='{$sLanguageCode}' ", array('question_order'));
                                foreach ($mearesult->readAll() as $mearow) {

                                    $question['ANSWER'] .= "\t\t<tr class=\"$rowclass\">\n\t\t\t<th class=\"answertext\">{$mearow['question']}".self::_addsgqacode(" (".$fieldname.$mearow['title'].")")."</th>\n";
                                    $rowclass = alternation($rowclass, 'row');

                                    for ($i = 1; $i <= 10; $i++) {
                                        $question['ANSWER'] .= "\t\t\t<td>".self::_input_type_image('radio', $i)."</td>\n";
                                    }
                                    $question['ANSWER'] .= "\t\t</tr>\n";
                                    $j++;
                                }
                                $question['ANSWER'] .= "\t</tbody>\n</table>\n";
                                break;

                                // ==================================================================
                            case "C":  //ARRAY (YES/UNCERTAIN/NO)

                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please choose the appropriate response for each item:"));
                                $question['QUESTION_TYPE_HELP'] .= self::_array_filter_help($qidattributes, $sLanguageCode, $surveyid);
                                $answerwidth = (trim($qidattributes['answer_width']) != '') ? $qidattributes['answer_width'] : 33;
                                $question['ANSWER'] .= "\n<table class='table-print-answers table table-bordered'>\n\t<thead>\n\t\t<tr>\n";
                                $question['ANSWER'] .= "\t\t\t<td style='width:{$answerwidth}%'>{NOTEMPTY}</td>\n";
                                $question['ANSWER'] .= '<th>'.gT("Yes").self::_addsgqacode(" (Y)").'</th>';
                                $question['ANSWER'] .= '<th>'.gT("Uncertain").self::_addsgqacode(" (U)").'</th>';
                                $question['ANSWER'] .= '<th>'.gT("No").self::_addsgqacode(" (N)").'</th>';
                                $question['ANSWER'] .= "\t</tr></thead>\n\n\t<tbody>\n";

                                $j = 0;

                                $rowclass = 'ls-odd';

                                $mearesult = Question::model()->getAllRecords(" parent_qid='{$deqrow['qid']}'  AND language='{$sLanguageCode}' ", array('question_order'));
                                foreach ($mearesult->readAll() as $mearow) {
                                    $question['ANSWER'] .= "\t\t<tr class=\"$rowclass\">\n";
                                    $question['ANSWER'] .= "\t\t\t<th class=\"answertext\">{$mearow['question']}".self::_addsgqacode(" (".$fieldname.$mearow['title'].")")."</th>\n";
                                    $question['ANSWER'] .= "\t\t\t<td>".self::_input_type_image('radio', gT("Yes"))."</td>\n";
                                    $question['ANSWER'] .= "\t\t\t<td>".self::_input_type_image('radio', gT("Uncertain"))."</td>\n";
                                    $question['ANSWER'] .= "\t\t\t<td>".self::_input_type_image('radio', gT("No"))."</td>\n";
                                    $question['ANSWER'] .= "\t\t</tr>\n";

                                    $j++;
                                    $rowclass = alternation($rowclass, 'row');
                                }
                                $question['ANSWER'] .= "\t</tbody>\n</table>\n";
                                break;

                            case "E":  //ARRAY (Increase/Same/Decrease)
                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please choose the appropriate response for each item:"));
                                $question['QUESTION_TYPE_HELP'] .= self::_array_filter_help($qidattributes, $sLanguageCode, $surveyid);
                                $answerwidth = (trim($qidattributes['answer_width']) != '') ? $qidattributes['answer_width'] : 33;
                                $question['ANSWER'] .= "\n<table class='table-print-answers table table-bordered'>\n\t<thead>\n\t\t<tr>\n";
                                $question['ANSWER'] .= "\t\t\t<td style='width:{$answerwidth}%'>{NOTEMPTY}</td>\n";
                                $question['ANSWER'] .= '<th>'.gT("Increase").self::_addsgqacode(" (I)").'</th>';
                                $question['ANSWER'] .= '<th>'.gT("Same").self::_addsgqacode(" (S)").'</th>';
                                $question['ANSWER'] .= '<th>'.gT("Decrease").self::_addsgqacode(" (D)").'</th>';
                                $question['ANSWER'] .= "\t</tr></thead>\n\n\t<tbody>\n";

                                $j = 0;
                                $rowclass = 'ls-odd';

                                $mearesult = Question::model()->getAllRecords(" parent_qid='{$deqrow['qid']}'  AND language='{$sLanguageCode}' ", array('question_order'));
                                foreach ($mearesult->readAll() as $mearow) {
                                    $question['ANSWER'] .= "\t\t<tr class=\"$rowclass\">\n";
                                    $question['ANSWER'] .= "\t\t\t<th class=\"answertext\">{$mearow['question']}".self::_addsgqacode(" (".$fieldname.$mearow['title'].")")."</th>\n";
                                    $question['ANSWER'] .= "\t\t\t<td>".self::_input_type_image('radio', gT("Increase"))."</td>\n";
                                    $question['ANSWER'] .= "\t\t\t<td>".self::_input_type_image('radio', gT("Same"))."</td>\n";
                                    $question['ANSWER'] .= "\t\t\t<td>".self::_input_type_image('radio', gT("Decrease"))."</td>\n";
                                    $question['ANSWER'] .= "\t\t</tr>\n";
                                    $j++;
                                    $rowclass = alternation($rowclass, 'row');
                                }
                                $question['ANSWER'] .= "\t</tbody>\n</table>\n";
                                break;

                                // ==================================================================
                            case ":": //ARRAY (Multi Flexible) (Numbers)
                                $width = (isset($qidattributes['input_size']) && $qidattributes['input_size']) ? $qidattributes['input_size'] : null;
                                if ($qidattributes['multiflexible_checkbox'] != 0) {
                                    $checkboxlayout = true;
                                } else {
                                    $checkboxlayout = false;
                                }

                                $question['QUESTION_TYPE_HELP'] .= self::_array_filter_help($qidattributes, $sLanguageCode, $surveyid);

                                $answerwidth = (trim($qidattributes['answer_width']) != '') ? $qidattributes['answer_width'] : 33;
                                $question['ANSWER'] .= "\n<table class='table-print-answers table table-bordered'>\n\t<thead>\n\t\t<tr>\n";
                                $question['ANSWER'] .= "\t\t\t<td style='width:{$answerwidth}%'>{NOTEMPTY}</td>\n";
                                $fresult = Question::model()->getAllRecords(" parent_qid='{$deqrow['qid']}' and scale_id=1 AND language='{$sLanguageCode}' ", array('question_order'));
                                $fresult = $fresult->readAll();
                                $fcount = count($fresult);
                                $i = 0;
                                //array to temporary store X axis question codes
                                $xaxisarray = array();
                                foreach ($fresult as $frow) {
                                    $question['ANSWER'] .= "\t\t\t<th>{$frow['question']}</th>\n";
                                    $i++;

                                    //add current question code
                                    $xaxisarray[$i] = $frow['title'];
                                }
                                $question['ANSWER'] .= "\t\t</tr>\n\t</thead>\n\n\t<tbody>\n";
                                $a = 1; //Counter for pdfoutput
                                $rowclass = 'ls-odd';

                                $mearesult = Question::model()->getAllRecords(" parent_qid='{$deqrow['qid']}' and scale_id=0 AND language='{$sLanguageCode}' ", array('question_order'));
                                $result = $mearesult->readAll();
                                foreach ($result as $frow) {
                                    $question['ANSWER'] .= "\t<tr class=\"$rowclass\">\n";
                                    $rowclass = alternation($rowclass, 'row');

                                    $answertext = $frow['question'];
                                    if (strpos($answertext, '|')) {$answertext = substr($answertext, 0, strpos($answertext, '|')); }
                                    $question['ANSWER'] .= "\t\t\t\t\t<th class=\"answertext\">$answertext</th>\n";
                                    //$printablesurveyoutput .="\t\t\t\t\t<td>";
                                    for ($i = 1; $i <= $fcount; $i++) {

                                        $question['ANSWER'] .= "\t\t\t<td>\n";
                                        if ($checkboxlayout === false) {
                                            $question['ANSWER'] .= "\t\t\t\t".self::_input_type_image('text', '', $width).self::_addsgqacode(" (".$fieldname.$frow['title']."_".$xaxisarray[$i].") ")."\n";
                                        } else {
                                            $question['ANSWER'] .= "\t\t\t\t".self::_input_type_image('checkbox').self::_addsgqacode(" (".$fieldname.$frow['title']."_".$xaxisarray[$i].") ")."\n";
                                        }
                                        $question['ANSWER'] .= "\t\t\t</td>\n";
                                    }
                                    $answertext = $frow['question'];
                                    if (strpos($answertext, '|')) {
                                        $answertext = substr($answertext, strpos($answertext, '|') + 1);
                                        $question['ANSWER'] .= "\t\t\t<th class=\"answertextright\">$answertext</th>\n";
                                    }
                                    $question['ANSWER'] .= "\t\t</tr>\n";
                                    $a++;
                                }
                                $question['ANSWER'] .= "\t</tbody>\n</table>\n";
                                break;

                                // ==================================================================
                            case ";": //ARRAY (Multi Flexible) (text)
                                $width = (isset($qidattributes['input_size']) && $qidattributes['input_size']) ? $qidattributes['input_size'] : null;
                                $mearesult = Question::model()->getAllRecords(" parent_qid='{$deqrow['qid']}' AND scale_id=0 AND language='{$sLanguageCode}' ", array('question_order'));
                                $mearesult = $mearesult->readAll();

                                $question['QUESTION_TYPE_HELP'] .= self::_array_filter_help($qidattributes, $sLanguageCode, $surveyid);

                                $answerwidth = (trim($qidattributes['answer_width']) != '') ? $qidattributes['answer_width'] : 33;
                                $question['ANSWER'] .= "\n<table class='table-print-answers table table-bordered'>\n\t<thead>\n\t\t<tr>\n";
                                $question['ANSWER'] .= "\t\t\t<td style='width:{$answerwidth}%'>{NOTEMPTY}</td>\n";
                                $fresult = Question::model()->getAllRecords(" parent_qid='{$deqrow['qid']}'  AND scale_id=1 AND language='{$sLanguageCode}' ", array('question_order'));
                                $fresult = $fresult->readAll();
                                $fcount = count($fresult);
                                $i = 0;
                                //array to temporary store X axis question codes
                                $xaxisarray = array();
                                foreach ($fresult as $frow) {
                                    $question['ANSWER'] .= "\t\t\t<th>{$frow['question']}</th>\n";
                                    $i++;

                                    //add current question code
                                    $xaxisarray[$i] = $frow['title'];
                                }
                                $question['ANSWER'] .= "\t\t</tr>\n\t</thead>\n\n<tbody>\n";
                                $a = 1;
                                $rowclass = 'ls-odd';

                                foreach ($mearesult as $mearow) {
                                    $question['ANSWER'] .= "\t\t<tr class=\"$rowclass\">\n";
                                    $rowclass = alternation($rowclass, 'row');
                                    $answertext = $mearow['question'];
                                    if (strpos($answertext, '|')) {$answertext = substr($answertext, 0, strpos($answertext, '|')); }
                                    $question['ANSWER'] .= "\t\t\t<th class=\"answertext\">$answertext</th>\n";

                                    for ($i = 1; $i <= $fcount; $i++) {
                                        $question['ANSWER'] .= "\t\t\t<td>\n";
                                        $question['ANSWER'] .= "\t\t\t\t".self::_input_type_image('text', '', $width).self::_addsgqacode(" (".$fieldname.$mearow['title']."_".$xaxisarray[$i].") ")."\n";
                                        $question['ANSWER'] .= "\t\t\t</td>\n";
                                    }
                                    $answertext = $mearow['question'];
                                    if (strpos($answertext, '|')) {
                                        $answertext = substr($answertext, strpos($answertext, '|') + 1);
                                        $question['ANSWER'] .= "\t\t\t\t<th class=\"answertextright\">$answertext</th>\n";
                                    }
                                    $question['ANSWER'] .= "\t\t</tr>\n";
                                    $a++;
                                }
                                $question['ANSWER'] .= "\t</tbody>\n</table>\n";
                                break;

                                // ==================================================================
                            case "F": //ARRAY (Flexible Labels)
                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please choose the appropriate response for each item:"));
                                $question['QUESTION_TYPE_HELP'] .= self::_array_filter_help($qidattributes, $sLanguageCode, $surveyid);

                                $fresult = Answer::model()->getAllRecords(" scale_id=0 AND qid='{$deqrow['qid']}'  AND language='{$sLanguageCode}'", array('sortorder', 'code'));
                                $fresult = $fresult->readAll();
                                $fcount = count($fresult);
                                $i = 1;
                                $column_headings = array();
                                foreach ($fresult as $frow) {
                                    $column_headings[] = $frow['answer'].self::_addsgqacode(" (".$frow['code'].")");
                                }
                                if (trim($qidattributes['answer_width']) != '') {
                                    $iAnswerWidth = 100 - $qidattributes['answer_width'];
                                } else {
                                    $iAnswerWidth = 77;
                                }
                                if (count($column_headings) > 0) {
                                    $col_width = round($iAnswerWidth / count($column_headings));

                                } else {
                                    $heading = '';
                                }
                                $answerwidth = (trim($qidattributes['answer_width']) != '') ? $qidattributes['answer_width'] : 33;
                                $question['ANSWER'] .= "\n<table class='table-print-answers table table-bordered'>\n\t<thead>\n\t\t<tr>\n";
                                $question['ANSWER'] .= "\t\t\t<td style='width:{$answerwidth}%'>{NOTEMPTY}</td>\n";
                                foreach ($column_headings as $heading) {
                                    $question['ANSWER'] .= "\t\t\t<th style=\"width:$col_width%;\">$heading</th>\n";
                                }
                                $i++;
                                $question['ANSWER'] .= "\t\t</tr>\n\t</thead>\n\n\t<tbody>\n";
                                $counter = 1;
                                $rowclass = 'ls-odd';

                                $mearesult = Question::model()->getAllRecords(" parent_qid='{$deqrow['qid']}'  AND language='{$sLanguageCode}' ", array('question_order'));
                                foreach ($mearesult->readAll() as $mearow) {
                                    $question['ANSWER'] .= "\t\t<tr class=\"$rowclass\">\n";
                                    $rowclass = alternation($rowclass, 'row');

                                    //semantic differential question type?
                                    if (strpos($mearow['question'], '|')) {
                                        $answertext = substr($mearow['question'], 0, strpos($mearow['question'], '|')).self::_addsgqacode(" (".$fieldname.$mearow['title'].")")." ";
                                    } else {
                                        $answertext = $mearow['question'].self::_addsgqacode(" (".$fieldname.$mearow['title'].")");
                                    }

                                    $question['ANSWER'] .= "\t\t\t<th class=\"answertext\">$answertext</th>\n";

                                    for ($i = 1; $i <= $fcount; $i++) {
                                        $question['ANSWER'] .= "\t\t\t<td>".self::_input_type_image('radio')."</td>\n";

                                    }
                                    $counter++;

                                    $answertext = $mearow['question'];

                                    //semantic differential question type?
                                    if (strpos($mearow['question'], '|')) {
                                        $answertext2 = substr($mearow['question'], strpos($mearow['question'], '|') + 1);
                                        $question['ANSWER'] .= "\t\t\t<th class=\"answertextright\">$answertext2</th>\n";
                                    }
                                    $question['ANSWER'] .= "\t\t</tr>\n";
                                }
                                $question['ANSWER'] .= "\t</tbody>\n</table>\n";
                                break;

                                // ==================================================================
                            case "1": //ARRAY (Flexible Labels) multi scale

                                $leftheader = $qidattributes['dualscale_headerA'][$sLanguageCode];
                                $rightheader = $qidattributes['dualscale_headerB'][$sLanguageCode];

                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please choose the appropriate response for each item:"));
                                $question['QUESTION_TYPE_HELP'] .= self::_array_filter_help($qidattributes, $sLanguageCode, $surveyid);

                                $answerwidth = (trim($qidattributes['answer_width']) != '') ? $qidattributes['answer_width'] : 33;
                                $question['ANSWER'] .= "\n<table class='table-print-answers table table-bordered'>\n\t<thead>\n\t\t<tr>\n";

                                $condition = "qid= '{$deqrow['qid']}'  AND language= '{$sLanguageCode}' AND scale_id=0";
                                $fresult = Answer::model()->getAllRecords($condition, array('sortorder', 'code'));
                                $fresult = $fresult->readAll();
                                $fcount = count($fresult);

                                $l1 = 0;
                                $printablesurveyoutput2 = "<td style='width:{$answerwidth}%'>{NOTEMPTY}</td>";
                                $myheader2 = '';
                                foreach ($fresult as $frow) {
                                    $printablesurveyoutput2 .= "\t\t\t<th>{$frow['answer']}".self::_addsgqacode(" (".$frow['code'].")")."</th>\n";
                                    $myheader2 .= "<td></td>";
                                    $l1++;
                                }
                                // second scale
                                $printablesurveyoutput2 .= "\t\t\t<td>{NOTEMPTY}</td>\n";
                                //$fquery1 = "SELECT * FROM {{answers}} WHERE qid='{$deqrow['qid']}'  AND language='{$sLanguageCode}' AND scale_id=1 ORDER BY sortorder, code";
                                // $fresult1 = Yii::app()->db->createCommand($fquery1)->query();
                                $fresult1 = Answer::model()->getAllRecords(" qid='{$deqrow['qid']}'  AND language='{$sLanguageCode}' AND scale_id=1 ", array('sortorder', 'code'));
                                $fresult1 = $fresult1->readAll();
                                $fcount1 = count($fresult1);
                                $l2 = 0;

                                //array to temporary store second scale question codes
                                $scale2array = array();
                                foreach ($fresult1 as $frow1) {
                                    $printablesurveyoutput2 .= "\t\t\t<th>{$frow1['answer']}".self::_addsgqacode(" (".$frow1['code'].")")."</th>\n";

                                    //add current question code
                                    $scale2array[$l2] = $frow1['code'];

                                    $l2++;
                                }
                                // build header if needed
                                if ($leftheader != '' || $rightheader != '') {
                                    $myheader = "\t\t\t<td style='width:{$answerwidth}%'>{NOTEMPTY}</td>";
                                    $myheader .= "\t\t\t<th colspan=\"".$l1."\">$leftheader</th>\n";

                                    if ($rightheader != '') {
                                        // $myheader .= "\t\t\t\t\t" .$myheader2;
                                        $myheader .= "\t\t\t<td>{NOTEMPTY}</td>";
                                        $myheader .= "\t\t\t<th colspan=\"".$l2."\">$rightheader</td>\n";
                                    }

                                    $myheader .= "\t\t\t\t</tr>\n";
                                } else {
                                    $myheader = '';
                                }
                                $question['ANSWER'] .= $myheader."\t\t</tr>\n\n\t\t<tr>\n";
                                $question['ANSWER'] .= $printablesurveyoutput2;
                                $question['ANSWER'] .= "\t\t</tr>\n\t</thead>\n\n\t<tbody>\n";

                                $rowclass = 'ls-odd';

                                //counter for each subquestion
                                $sqcounter = 0;
                                $mearesult = Question::model()->getAllRecords(" parent_qid={$deqrow['qid']}  AND language='{$sLanguageCode}' ", array('question_order'));
                                foreach ($mearesult->readAll() as $mearow) {
                                    $question['ANSWER'] .= "\t\t<tr class=\"$rowclass\">\n";
                                    $rowclass = alternation($rowclass, 'row');
                                    $answertext = $mearow['question'].self::_addsgqacode(" (".$fieldname.$mearow['title']."#0) / (".$fieldname.$mearow['title']."#1)");
                                    if (strpos($answertext, '|')) {$answertext = substr($answertext, 0, strpos($answertext, '|')); }
                                    $question['ANSWER'] .= "\t\t\t<th class=\"answertext\">$answertext</th>\n";
                                    for ($i = 1; $i <= $fcount; $i++) {
                                        $question['ANSWER'] .= "\t\t\t<td>".self::_input_type_image('radio')."</td>\n";
                                    }
                                    $question['ANSWER'] .= "\t\t\t<td>{NOTEMPTY}</td>\n";
                                    for ($i = 1; $i <= $fcount1; $i++) {
                                        $question['ANSWER'] .= "\t\t\t<td>".self::_input_type_image('radio')."</td>\n";
                                    }

                                    $answertext = $mearow['question'];
                                    if (strpos($answertext, '|')) {
                                        $answertext = substr($answertext, strpos($answertext, '|') + 1);
                                        $question['ANSWER'] .= "\t\t\t<th class=\"answertextright\">$answertext</th>\n";
                                    }
                                    $question['ANSWER'] .= "\t\t</tr>\n";

                                    //increase subquestion counter
                                    $sqcounter++;
                                }
                                $question['ANSWER'] .= "\t</tbody>\n</table>\n";
                                break;

                                // ==================================================================
                            case "H": //ARRAY (Flexible Labels) by Column

                                $condition = "parent_qid= '{$deqrow['qid']}'  AND language= '{$sLanguageCode}'";
                                $fresult = Question::model()->getAllRecords($condition, array('question_order', 'title'));
                                $fresult = $fresult->readAll();
                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), gT("Please choose the appropriate response for each item:"));
                                $answerwidth = (trim($qidattributes['answer_width_bycolumn']) != '') ? $qidattributes['answer_width_bycolumn'] : 33;
                                $question['ANSWER'] .= "\n<table class='table-print-answers table table-bordered'>\n\t<thead>\n\t\t<tr>\n";
                                $question['ANSWER'] .= "\t\t\t<td style='width:{$answerwidth}%'>{NOTEMPTY}</td>\n";

                                $fcount = count($fresult);
                                $i = 0;
                                foreach ($fresult as $frow) {
                                    $question['ANSWER'] .= "\t\t\t<th>{$frow['question']}".self::_addsgqacode(" (".$fieldname.$frow['title'].")")."</th>\n";
                                    $i++;
                                }
                                $question['ANSWER'] .= "\t\t</tr>\n\t</thead>\n\n\t<tbody>\n";
                                $a = 1;
                                $rowclass = 'ls-odd';

                                $mearesult = Answer::model()->getAllRecords(" qid='{$deqrow['qid']}' AND scale_id=0 AND language='{$sLanguageCode}' ", array('sortorder', 'code'));
                                foreach ($mearesult->readAll() as $mearow) {
                                    //$_POST['type']=$type;
                                    $question['ANSWER'] .= "\t\t<tr class=\"$rowclass\">\n";
                                    $rowclass = alternation($rowclass, 'row');
                                    $question['ANSWER'] .= "\t\t\t<th class=\"answertext\">{$mearow['answer']}".self::_addsgqacode(" (".$mearow['code'].")")."</th>\n";
                                    //$printablesurveyoutput .="\t\t\t\t\t<td>";
                                    for ($i = 1; $i <= $fcount; $i++) {
                                        $question['ANSWER'] .= "\t\t\t<td>".self::_input_type_image('radio')."</td>\n";
                                    }
                                    //$printablesurveyoutput .="\t\t\t\t\t</tr></table></td>\n";
                                    $question['ANSWER'] .= "\t\t</tr>\n";
                                    $a++;
                                }
                                $question['ANSWER'] .= "\t</tbody>\n</table>\n";

                                break;
                            case "|":   // File Upload
                                $question['QUESTION_TYPE_HELP'] .= CHtml::tag("div", array("class"=>"tip-help"), "Kindly attach the aforementioned documents along with the survey");
                                break;
                                // === END SWITCH ===================================================
                        }

                        $question['QUESTION_TYPE_HELP'] = self::_star_replace($question['QUESTION_TYPE_HELP']); // WTF ?
                        $group['QUESTIONS'] .= self::_populate_template($oTemplate, 'question', $question);

                    }
                    if ($bGroupHasVisibleQuestions) {
                        $survey_output['GROUPS'] .= self::_populate_template($oTemplate, 'group', $group);
                    }
            }

            $survey_output['THEREAREXQUESTIONS'] = str_replace('{NUMBEROFQUESTIONS}', $total_questions, gT('There are {NUMBEROFQUESTIONS} questions in this survey'));

            // START recursive tag stripping.
            // PHP 5.1.0 introduced the count parameter for preg_replace() and thus allows this procedure to run with only one regular expression.
            // Previous version of PHP needs two regular expressions to do the same thing and thus will run a bit slower.
            $server_is_newer = version_compare(PHP_VERSION, '5.1.0', '>');
            $rounds = 0;
            /* Why we do this ????: why remove emty th/td ? */
            while ($rounds < 1) {
                $replace_count = 0;
                if ($server_is_newer) {
// Server version of PHP is at least 5.1.0 or newer

                    $survey_output['GROUPS'] = preg_replace(
                    array(
                                                '/<td>(?:&nbsp;|&#160;| )?<\/td>/isU'
                                                ,'/<th[^>]*>(?:&nbsp;|&#160;| )?<\/th>/isU'
                                                ,'/<([^ >]+)[^>]*>(?:&nbsp;|&#160;|\r\n|\n\r|\n|\r|\t| )*<\/\1>/isU'
                                                )
                                                ,array(
                                                '[[EMPTY-TABLE-CELL]]'
                                                ,'[[EMPTY-TABLE-CELL-HEADER]]'
                                                ,''
                                                )
                                                ,$survey_output['GROUPS']
                                                ,-1
                                                ,$replace_count
                                                );
                } else {
                    // Server version of PHP is older than 5.1.0
                    $survey_output['GROUPS'] = preg_replace(
                    array(
                                                '/<td>(?:&nbsp;|&#160;| )?<\/td>/isU'
                                                ,'/<th[^>]*>(?:&nbsp;|&#160;| )?<\/th>/isU'
                                                ,'/<([^ >]+)[^>]*>(?:&nbsp;|&#160;|\r\n|\n\r|\n|\r|\t| )*<\/\1>/isU'
                                                )
                                                ,array(
                                                '[[EMPTY-TABLE-CELL]]'
                                                ,'[[EMPTY-TABLE-CELL-HEADER]]'
                                                ,''
                                                )
                                                ,$survey_output['GROUPS']
                                                );
                                                $replace_count = preg_match(
                                        '/<([^ >]+)[^>]*>(?:&nbsp;|&#160;|\r\n|\n\r|\n|\r|\t| )*<\/\1>/isU'
                                        , $survey_output['GROUPS']
                                        );
                }

                if ($replace_count == 0) {
                    ++$rounds;
                    $survey_output['GROUPS'] = preg_replace(
                    array(
                                        '/\[\[EMPTY-TABLE-CELL\]\]/'
                                        ,'/\[\[EMPTY-TABLE-CELL-HEADER\]\]/'
                                        ,'/\n(?:\t*\n)+/'
                                        )
                                        ,array(
                                        '<td>&nbsp;</td>'
                                        ,'<th>&nbsp;</th>'
                                        ,"\n"
                                        )
                                        ,$survey_output['GROUPS']
                                        );

                }
            }

            $survey_output['GROUPS'] = preg_replace('/(<div[^>]*>){NOTEMPTY}(<\/div>)/', '\1&nbsp;\2', $survey_output['GROUPS']);
            $survey_output['GROUPS'] = preg_replace('/(<td[^>]*>){NOTEMPTY}(<\/td>)/', '\1&nbsp;\2', $survey_output['GROUPS']);

            // END recursive empty tag stripping.

            echo self::_populate_template($oTemplate, 'survey', $survey_output);
        }// End print

    }

    /**
     * A poor mans templating system.
     *
     *     $template    template filename (path is privided by config.php)
     *     $input        a key => value array containg all the stuff to be put into the template
     *     $line         for debugging purposes only.
     *
     * Returns a formatted string containing template with
     * keywords replaced by variables.
     *
     * How:
     * @param string $template
     * @param TemplateConfiguration $oTemplate
     */
    private function _populate_template($oTemplate, $template, $input, $line = '')
    {
        $full_path = $oTemplate->pstplPath.DIRECTORY_SEPARATOR.'print_'.$template.'.pstpl';
        $full_constant = 'TEMPLATE'.$template.'.pstpl';
        if (!defined($full_constant)) {
            if (is_file($full_path)) {
                define($full_constant, file_get_contents($full_path));

                $template_content = constant($full_constant);
                $test_empty = trim($template_content);
                if (empty($test_empty)) {
                    return "<!--\n\t$full_path\n\tThe template was empty so is useless.\n-->";
                }
            } else {
                // No template found, abort
                throw new \Exception("No template file found at path ".$full_path);
            }
        } else {
            $template_content = constant($full_constant);
            $test_empty = trim($template_content);
            if (empty($test_empty)) {
                return "<!--\n\t$full_path\n\tThe template was empty so is useless.\n-->";
            }
        }

        if (is_array($input)) {
            foreach ($input as $key => $value) {
                $find[] = '{'.$key.'}';
                $replace[] = $value;
            }
            return str_replace($find, $replace, $template_content);
        } else {
            if (Yii::app()->getConfig('debug') > 0) {
                if (!empty($line)) {
                    $line = 'LINE '.$line.': ';
                }
                return '<!-- '.$line.'There was nothing to put into the template -->'."\n";
            }
        }

    }

    private function _min_max_answers_help($qidattributes, $sLanguageCode, $surveyid)
    {
        $output = "";
        if (!empty($qidattributes['min_answers'])) {
            $output .= "\n<div class='extrahelp'>".sprintf(gT("Please choose at least %s items."), $qidattributes['min_answers'])."</div>\n";
        }
        if (!empty($qidattributes['max_answers'])) {
            $output .= "\n<div class='extrahelp'>".sprintf(gT("Please choose no more than %s items."), $qidattributes['max_answers'])."</div>\n";
        }
        return $output;
    }


    /**
     * @param string $type
     * @param string $type question type
     * @param string|null title : optionnable title
     * @param integer|null size (or cols) of input (text|textarea)
     * @param integer|null rows number of rows  (text|textarea)
     */
    private function _input_type_image($type, $title = null, $size = null, $rows = null)
    {
        if (!$size && ($type == 'other' or $type == 'othercomment')) {
            $size = 20;
        }
        if ($rows < 1) {
            $rows = 1;
        }

        /* How di this work ? */
        if (!empty($title)) {
            $div_title = ' title="'.htmlspecialchars($title).'"';
        } else {
            $div_title = '';
        }

        switch ($type) {
            case 'textarea':
            case 'text':
                if ($size) {
                    $width = "width:".($size * 2)."em;";
                } else {
                    $width = "";
                }
                if ($rows) {
                    $height = "height:".($rows * 2 + 1)."em;";
                } else {
                    $height = ""; /* can never happen */
                }
                $style = " style='{$width}{$height}'";
                break;
            case 'rank':
                $style = " style='width:8em;height:3em'";
                break;
            case 'other':
            case 'othercomment':
                $style = " style='width:30em;height:3em'";
                break;
            default:
                $style = '';
        }
        switch ($type) {
            case 'radio':
            case 'checkbox':
                $output = '<div class="input-'.$type.'">{NOTEMPTY}</div>';
                break;
            case 'rank':
            case 'other':
            case 'othercomment':
            case 'text':
            case 'textarea':
                $output = '<div class="form-control print-control input-'.$type.'"'.$style.$div_title.'>{NOTEMPTY}</div>';
                break;
            default:
                $output = '';
        }
        return $output;
    }
    /**
     * Get the final column width
     * @param integer|string $answerBaseWidth by attribute
     * @param integer|string $labelBaseWidth by attribute
     *
     * @return integer[] label width, answer wrapper width
     */
    private function getColumnWidth($answerBaseWidth, $labelBaseWidth)
    {
        if (intval($answerBaseWidth) < 1 || intval($answerBaseWidth) > 12) {
            $answerBaseWidth = null;
        }
        if (intval($labelBaseWidth) < 1 || intval($labelBaseWidth) > 12) {
            $labelBaseWidth = null;
        }
        if (!$answerBaseWidth && !$labelBaseWidth) {
            $sInputContainerWidth = 8;
            $sLabelWidth = 4;
        } else {
            if ($answerBaseWidth) {
                $sInputContainerWidth = $answerBaseWidth;
            } elseif ($attributeLabelWidth == 12) {
                $sInputContainerWidth = 12;
            } else {
                $sInputContainerWidth = 12 - $attributeLabelWidth;
            }
            if ($labelBaseWidth) {
                $sLabelWidth = $labelBaseWidth;
            } elseif ($answerBaseWidth == 12) {
                $sLabelWidth = 12;
            } else {
                $sLabelWidth = 12 - $sInputContainerWidth;
            }
        }
        return array(
            'label'=>$sLabelWidth,
            'answer'=>$sInputContainerWidth
        );
    }
    private function _star_replace($input)
    {
        return preg_replace(
                    '/\*(.*)\*/U'
                    ,'<strong>\1</strong>'
                    ,$input
                    );
    }

    private function _array_filter_help($qidattributes, $sLanguageCode, $surveyid)
    {
        $output = "";
        if (!empty($qidattributes['array_filter'])) {
            $aFilter = explode(';', $qidattributes['array_filter']);
            $output .= "\n<div class='extrahelp'>";
            foreach ($aFilter as $sFilter) {
                $oQuestion = Question::model()->findByAttributes(array('title' => $sFilter, 'language' => $sLanguageCode, 'sid' => $surveyid));
                if ($oQuestion) {
                    $sNewQuestionText = flattenText(breakToNewline($oQuestion->getAttribute('question')));
                    $output .= sprintf(gT("Only answer this question for the items you selected in question %s ('%s')"), $qidattributes['array_filter'], $sNewQuestionText);

                }
            }
            $output .= "</div>\n";
        }
        if (!empty($qidattributes['array_filter'])) {
            $aFilter = explode(';', $qidattributes['array_filter']);
            $output .= "\n<div class='extrahelp'>";
            foreach ($aFilter as $sFilter) {
                $oQuestion = Question::model()->findByAttributes(array('title' => $sFilter, 'language' => $sLanguageCode, 'sid' => $surveyid));
                if ($oQuestion) {
                    $sNewQuestionText = flattenText(breakToNewline($oQuestion->getAttribute('question')));
                    $output .= sprintf(gT("Only answer this question for the items you did not select in question %s ('%s')"), $qidattributes['array_filter'], $sNewQuestionText);
                }
            }
            $output .= "</div>\n";
        }
        return $output;
    }

    /*
     * $code: Text string containing the reference (column heading) for the current (sub-) question
     *
     * Checks if the $showsgqacode setting is enabled at config and adds references to the column headings
     * to the output so it can be used as a code book for customized SQL queries when analysing data.
     *
     * return: adds the text string to the overview
     */
    private function _addsgqacode($code)
    {
        $showsgqacode = Yii::app()->getConfig('showsgqacode');
        if (isset($showsgqacode) && $showsgqacode == true) {
            return $code;
        }
    }

}
