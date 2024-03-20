<?php
// Copyright (c) 2020 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg, GPLv3, see LICENSE

/**
 * Choice Evaluation
 */
class ilExteEvalQuestionStack extends ilExteEvalQuestion
{
    protected ilDBInterface $db;

	/**
	 * evaluation provides a single value for the overview level
	 */
	protected bool $provides_value = false;

	/**
	 * evaluation provides data for a details screen
	 */
	protected bool $provides_details = true;

	/**
	 * evaluation provides a chart
	 */
	protected bool $provides_chart = true;

	/**
	 * list of allowed test types, e.g. array(self::TEST_TYPE_FIXED)
	 */
	protected array $allowed_test_types = array();

	/**
	 * list of question types, e.g. array('assSingleChoice', 'assMultipleChoice', ...)
	 */
	protected array $allowed_question_types = array('assStackQuestion');

	/**
	 * specific prefix of language variables (lowercase classname is default)
	 */
	protected ?string $lang_prefix = 'qst_stack';

    /**
     * Constructor
     */
    public function __construct(ilExtendedTestStatisticsPlugin $a_plugin, ilExtendedTestStatisticsCache $a_cache)
    {
        global $DIC;
        $this->db = $DIC->database();

        parent::__construct($a_plugin, $a_cache);
    }

	/**
	 * Calculate the single value for a question (to be overwritten)
	 *
	 * Note:
	 * This function will be called for many questions in sequence
	 * - Please avoid instanciation of question objects
	 * - Please try to cache question independent intermediate results
	 */
    protected function calculateValue(int $a_question_id) : ilExteStatValue
	{
		return new ilExteStatValue;
	}


	/**
	 * Calculate the details question (to be overwritten)
	 */
    protected function calculateDetails(int $a_question_id) : ilExteStatDetails
	{
		/** @var assStackQuestion $question */
		$question = assQuestion::_instantiateQuestion($a_question_id);
		if (!is_object($question)) {
			return new ilExteStatDetails();
		}

		$raw_data = $this->getRawData($a_question_id);

		$data = $this->processData($raw_data, $question->prts, $question->getPoints());
		// answer details
		$details = new ilExteStatDetails();
		$details->columns = array(
			ilExteStatColumn::_create('prt', $this->txt('prt') . "-" . $this->txt('node'), ilExteStatColumn::SORT_TEXT),
			ilExteStatColumn::_create('model_response', $this->txt('model_response'), ilExteStatColumn::SORT_TEXT),
			ilExteStatColumn::_create('feedback_errors', $this->txt('feedback_errors'), ilExteStatColumn::SORT_TEXT),
			ilExteStatColumn::_create('partial', $this->txt('partial'), ilExteStatColumn::SORT_TEXT),
			ilExteStatColumn::_create('count', $this->txt('count'), ilExteStatColumn::SORT_NUMBER),
			ilExteStatColumn::_create('frequency', $this->txt('frequency'), ilExteStatColumn::SORT_NUMBER)
		);
		$details->chartType = ilExteStatDetails::CHART_BARS;
		$details->chartLabelsColumn = 3;

		foreach ($data as $key => $option) {
			$details->rows[] = array(
				'prt' => ilExteStatValue::_create($option["prt"] . "-" .($option["node"] ?? ''), ilExteStatValue::TYPE_TEXT, 0),
				'model_response' => ilExteStatValue::_create($option["answernote"] ?? '', ilExteStatValue::TYPE_TEXT, 0),
				'feedback_errors' => ilExteStatValue::_create($option["feedback"] ?? '', ilExteStatValue::TYPE_TEXT, 0),
				'partial' => ilExteStatValue::_create((string) ($option["partial"] ?? ''), ilExteStatValue::TYPE_TEXT, 0),
				'count' => ilExteStatValue::_create((string) ($option["count"] ?? ''), ilExteStatValue::TYPE_NUMBER, 0),
				'frequency' => ilExteStatValue::_create((string) ($option["frequency"] ?? ''), ilExteStatValue::TYPE_NUMBER, 2),
			);
		}

		return $details;
	}

	public function processData($raw_data, $prts, $points)
	{
		$data = array();
		$points_structure = $this->getPointsStructure($prts, $points);
		foreach ($prts as $prt_name => $prt_obj) {
			foreach ($prt_obj->get_nodes() as $node_name => $node) {
				$node_name = (string)$node_name;
				//FALSE
				$data[$prt_name . "-" . $node_name . "-F"] = array("prt" => $prt_name, "node" => $node_name, "answernote" => $prt_name . "-" . $node_name . "-F", "feedback" => "", "count" => 0, "frequency" => 0, "partial" => ($points_structure[$prt_name][$node_name]["false_mode"] ?? '') . ($points_structure[$prt_name][$node_name]["false_value"] ?? ''));
				//TRUE
				$data[$prt_name . "-" . $node_name . "-T"] = array("prt" => $prt_name, "node" => $node_name, "answernote" => $prt_name . "-" . $node_name . "-T", "feedback" => "", "count" => 0, "frequency" => 0, "partial" => ($points_structure[$prt_name][$node_name]["true_mode"] ?? '') . ($points_structure[$prt_name][$node_name]["true_value"] ?? ''));
				//NO ANSWER
				$data[$prt_name . "-" . $node_name . "-NoAnswer"] = array("prt" => $prt_name, "node" => $node_name, "answernote" => $this->txt("no_answer"), "feedback" => "", "count" => 0, "frequency" => 0, "partial" => "");
			}
		}

		foreach ($raw_data as $attempt) {
			foreach ($prts as $prt_name => $prt_obj) {
				//ANSWERNOTE
				if (isset($attempt["xqcas_prt_" . $prt_name . "_answernote"])) {
					$answer_note = $this->processAnswerNote($attempt["xqcas_prt_" . $prt_name . "_answernote"]);

					foreach ($prt_obj->get_nodes() as $node_name => $node) {
						$node_name = (string) $node_name;
                        $key_f = $prt_name . "-" . $node_name . "-F";
                        $key_t = $prt_name . "-" . $node_name . "-T";
                        $key_n = $prt_name . "-" . $node_name . "-NoAnswer";

                        $data[$key_f]["count"] = $data[$key_f]["count"] ?? 0;
                        $data[$key_t]["count"] = $data[$key_t]["count"] ?? 0;
                        $data[$key_n]["count"] = $data[$key_n]["count"] ?? 0;

						//FALSE
						if ($key_f == $answer_note or (stripos($answer_note, "-" . $node_name . "-F") !== FALSE)) {
							$data[$key_f]["count"]++;
							if (stripos($answer_note, "-" . $node_name . "-F")) {
								if (empty($data[$key_f]["feedback"])) {
									$data[$key_f]["feedback"] = $answer_note;
								} else {
									$data[$key_f]["feedback"] .= " / " . $answer_note;
								}
							}
							//RECALCULATE frequency
							$total = (float) $data[$key_f]["count"] + $data[$key_t]["count"] + $data[$key_n]["count"];
							$data[$key_f]["frequency"] = ((float)$data[$key_f]["count"] * 100) / $total;
							$data[$key_t]["frequency"] = ((float)$data[$key_t]["count"] * 100) / $total;
							$data[$key_n]["frequency"] = ((float)$data[$key_n]["count"] * 100) / $total;
							continue;

						}
						//TRUE
						if ($key_t == $answer_note or (stripos($answer_note, "-" . $node_name . "-T") !== FALSE)) {
							$data[$key_t]["count"]++;
							if (stripos($answer_note, "-" . $node_name . "-T")) {
								if (empty($data[$key_t]["feedback"])) {
									$data[$key_t]["feedback"] = $answer_note;
								} else {
									$data[$key_t]["feedback"] .= " / " . $answer_note;
								}
							}
							//RECALCULATE frequency
							$total = (float)$data[$key_f]["count"] + $data[$key_t]["count"] + $data[$key_n]["count"];
							$data[$key_f]["frequency"] = ((float)$data[$key_f]["count"] * 100) / $total;
							$data[$key_t]["frequency"] = ((float)$data[$key_t]["count"] * 100) / $total;
							$data[$key_n]["frequency"] = ((float)$data[$key_n]["count"] * 100) / $total;
							continue;
						}
						//NO ANSWER
						$data[$key_n]["count"]++;
						//$data[$key_n]["feedback"] .= $answer_note;
						//RECALCULATE frequency
						$total = (float)$data[$key_f]["count"] + $data[$key_t]["count"] + $data[$key_n]["count"];
						$data[$key_f]["frequency"] = ((float)$data[$key_f]["count"] * 100) / $total;
						$data[$key_t]["frequency"] = ((float)$data[$key_t]["count"] * 100) / $total;
						$data[$key_n]["frequency"] = ((float)$data[$key_n]["count"] * 100) / $total;
					}

					//COUNT PRT
				}
			}
		}

		return $data;
	}

	/**
	 *
	 * Maxima seems to rename the nodes, prtx-0 is prtx-1 for maxima
	 * This function translate from maxima to ILIAS notation to avoid user confusion
	 * @param $raw_answernote
	 */
	public function processAnswerNote($raw_answernote)
	{

		$answer_note = str_replace("-1-", "-0-", $raw_answernote);
		$answer_note = str_replace("-2-", "-1-", $answer_note);
		$answer_note = str_replace("-3-", "-2-", $answer_note);
		$answer_note = str_replace("-4-", "-3-", $answer_note);
		$answer_note = str_replace("-5-", "-4-", $answer_note);
		$answer_note = str_replace("-6-", "-5-", $answer_note);
		$answer_note = str_replace("-7-", "-6-", $answer_note);
		$answer_note = str_replace("-8-", "-7-", $answer_note);
		$answer_note = str_replace("-9-", "-8-", $answer_note);
		$answer_note = str_replace("-10-", "-9-", $answer_note);
		$answer_note = str_replace("-11-", "-10-", $answer_note);
		$answer_note = str_replace("-12-", "-11-", $answer_note);
		$answer_note = str_replace("-13-", "-12-", $answer_note);
		$answer_note = str_replace("-14-", "-13-", $answer_note);
		$answer_note = str_replace("-15-", "-14-", $answer_note);
		$answer_note = str_replace("-16-", "-15-", $answer_note);

		return $answer_note;
	}

	public function getPointsStructure($prts, $question_points)
	{
		//Set variables
		$max_weight = 0.0;
		$structure = array();

		//Get max weight of the PRT
		foreach ($prts as $prt_name => $prt) {
			$max_weight += $prt->get_value();
		}

		//fill the structure
		foreach ($prts as $prt_name => $prt) {
			$prt_max_weight = $prt->get_value();
			$prt_max_points = ($prt_max_weight / $max_weight) * $question_points;
			$structure[$prt_name]['max_points'] = $prt_max_points;
			foreach ($prt->get_nodes() as $node_name => $node) {
				$structure[$prt_name][$node_name]['true_mode'] = $node->truescoremode;
				$structure[$prt_name][$node_name]['true_value'] = ($node->truescore * $prt_max_points);
				$structure[$prt_name][$node_name]['false_mode'] = $node->falsescoremode;
				$structure[$prt_name][$node_name]['false_value'] = ($node->falsescore * $prt_max_points);
			}
		}

		return $structure;
	}

	protected function getRawData($a_question_id)
	{
		/** @var assStackQuestion $question */
		$question = assQuestion::_instantiateQuestion($a_question_id);
		if (!is_object($question)) {
			return new ilExteStatDetails();
		}

		$raw_data = array();
		/** @var ilExteStatSourceAnswer $answer */
		foreach ($this->data->getAnswersForQuestion($a_question_id, true) as $answer) {
			$result = $this->db->queryF(
				"SELECT * FROM tst_solutions WHERE active_fi = %s AND pass = %s AND question_fi = %s",
				array("integer", "integer", "integer"),
				array($answer->active_id, $answer->pass, $a_question_id)
			);
			while ($data = $this->db->fetchAssoc($result)) {
				if (isset($data["value2"]) && isset($data["points"])) {
					$raw_data[$answer->active_id . "_" . $answer->pass][$data["value2"]] = $data["points"];
				}
                elseif (isset($data["value1"])) {
					$raw_data[$answer->active_id . "_" . $answer->pass][$data["value1"]] = $data["value2"] ?? null;
				}
			}
		}

		return $raw_data;
	}

	public function getExtraInfo($a_question_id)
	{
		/** @var assStackQuestion $question */
		$question = assQuestion::_instantiateQuestion($a_question_id);
		if (!is_object($question)) {
			return new ilExteStatDetails();
		}

		$raw_data = $this->getRawData($a_question_id);
		$text = $this->firstRequest($raw_data, $question);
		$text .= '</br>' . $this->secondRequest($raw_data, $question);
		$text .= '</br>' . $this->thirdRequest($raw_data, $question);
		return $text;
	}

	public function firstRequest($raw_data, assStackQuestion $question)
	{
		$data = array();

		//Prepare data array
		foreach ($question->prts as $prt_name => $value) {
			$data[$prt_name] = array('value' => array(), 'count' => 0);
		}

		//Fill data array
		foreach ($data as $prt_name => $value) {
			foreach ($raw_data as $activeid_pass => $user_answer) {
				//Answer Note
				if (isset($user_answer['xqcas_prt_' . $prt_name . '_answernote'])) {
					$data[$prt_name]['value'][$activeid_pass] = $user_answer['xqcas_prt_' . $prt_name . '_answernote'];
					$data[$prt_name]['count']++;
				}
			}
		}

		//Adjust data Array
		$view_data = array();
		foreach ($data as $prt_name => $prt) {
			$view_data[$prt_name] = array('count' => $prt['count'], 'answer_notes' => array());
			foreach (($prt['value'] ?? []) as $attempt => $answer_note) {
				if (!key_exists($answer_note, $view_data[$prt_name]['answer_notes'])) {
					$view_data[$prt_name]['answer_notes'][$answer_note]['value'] = 1;
					$view_data[$prt_name]['answer_notes'][$answer_note]['percentage'] = (float)($view_data[$prt_name]['answer_notes'][$answer_note]['value'] ?? 0) / $prt['count'];
				} else {
					$view_data[$prt_name]['answer_notes'][$answer_note]['value']++;
					$view_data[$prt_name]['answer_notes'][$answer_note]['percentage'] = ($view_data[$prt_name]['answer_notes'][$answer_note]['value'] ?? 0) / $prt['count'];
				}
			}
		}


		//Show request
		$text = '<div class="alert alert-info" role="alert">'.$this->txt('extra_1').'</div>';
		foreach ($view_data as $prt_name => $values) {
			$text .= '#' . $prt_name . ' (' . $view_data[$prt_name]['count'] . ')</br>';
			foreach ($values['answer_notes'] as $answer_note => $info) {
				$text .= $info['value'] . ' ( ' . round($info['percentage'] * 100, 2) . '% ) / ' . $answer_note . '  </br>';
			}
		}

		return $text;
	}

	public function secondRequest($raw_data, assStackQuestion $question)
	{
		$data = array();
		$pre_data = array();
		$data2 = array();

		//are there variants?
		$variants = false;
		foreach ($raw_data as $activeid_pass => $attempt) {
			foreach ($attempt as $key => $value) {
				foreach ($question->prts as $prt_name => $p_value) {
					//Get Variant
					$seed = 'xqcas_prt_' . $prt_name . '_seed';
					if (key_exists($seed, $attempt)) {
						if($attempt[$seed] === "0"){
							$pre_data[$attempt[$seed]][$activeid_pass] = $attempt;
						}else{
							$pre_data[$attempt[$seed]][$activeid_pass] = $attempt;
							$variants = true;
						}
					}
				}
			}
		}

		//Fill data array
		foreach ($pre_data as $variant => $attempt) {
			foreach ($attempt as $activeid_pass => $value) {
				foreach ($question->prts as $prt_name => $p_value) {
					$answer_note = 'xqcas_prt_' . $prt_name . '_answernote';
					if (key_exists($answer_note, $value)) {
						$data[$variant][$activeid_pass][$prt_name] = ($value[$answer_note] ?? null);
					}
					foreach ($question->inputs as $input_name => $i_value) {
						$user_answer = 'xqcas_prt_' . $prt_name . '_value_' . $input_name;
						if (key_exists($user_answer, $value)) {
							$data[$variant][$activeid_pass][$input_name] = ($value[$user_answer] ?? null);
							$data2[$input_name][$activeid_pass] = ($value[$user_answer] ?? null);
						}
					}
				}
			}
		}

		$this->data_view = $data2;

		if (!$variants) {
			return $this->txt('no_variants').'</br>';
		}

		//Adjust data
		$view_data_pre = array();
		$view_data = array();
		$input_answers = $question->inputs;
		foreach ($input_answers as $input_name => $object) {
			$view_data_pre['input'][$input_name] = array();
		}
		$PRT_answer_notes = $question->prts;
		foreach ($PRT_answer_notes as $prt_name => $object) {
			$view_data_pre['prt'][$prt_name] = array();
		}


		foreach ($data as $variant => $attempt) {
			foreach ($attempt as $activeid_pass => $values) {
				foreach ($values as $key => $value) {
					//Inputs
					if (array_key_exists($key, $view_data_pre['input'])) {
						if (!isset($view_data['input'][$key][$value])) {
							$view_data[$variant]['input'][$key][$value]['count'] = 1;
						} else {
							$view_data[$variant]['input'][$key][$value]['count']++;
						}
					}

					//PRT ANSWERNOTES
					if (array_key_exists($key, $view_data_pre['prt'])) {
						if (!isset($view_data['prt'][$key][$value])) {
							$view_data[$variant]['prt'][$key][$value]['count'] = 1;
						} else {
							$view_data[$variant]['prt'][$key][$value]['count']++;
						}
					}
				}
			}
		}


		//Show request
		$text = '<div class="alert alert-info" role="alert">'.$this->txt('extra_2').'</div>';

		foreach ($view_data as $variant => $attempt) {
			$text .= '<div class="alert alert-success" role="alert">'.$this->txt('variant').': '.$variant.'</div>';
			//Inputs
			if (isset($attempt['input'])) {
				foreach ($attempt['input'] as $input_name => $answers) {
					if (is_array($answers)) {
						$answer_num = sizeof($answers);
						$text .= '##' . $input_name . ' (' . $answer_num . ')</br>';
						foreach ($answers as $user_answer => $count_array) {
							if (isset($count_array['count'])) {
								$text .= $count_array['count'] . ' (' . round((sizeof($count_array) / $answer_num) * 100, 2) . '%); ' . $user_answer . '</br>';
							}
						}
					}
				}
			}
			$text .= '</br>';

			//PRT
			if (isset($attempt['prt'])) {
				foreach ($attempt['prt'] as $prt_name => $answer_notes) {
					if (is_array($answer_notes)) {
						$answernotes_num = sizeof($answer_notes);
						$text .= '##' . $prt_name . ' (' . $answernotes_num . ')</br>';
						foreach ($answer_notes as $answer_note => $count_array) {
							if (isset($count_array['count'])) {
								$text .= $count_array['count'] . ' (' . round((sizeof($count_array) / $answernotes_num) * 100, 2) . '%); ' . $answer_note . '</br>';
							}
						}
					}
				}
			}
		}

		return $text;
	}

	public function thirdRequest($raw_data, assStackQuestion $question)
	{

		//Show request
		$text = '<div class="alert alert-info" role="alert">'.$this->txt('extra_3').'</div>';

		$data = $this->data_view;
		foreach ($question->inputs as $input_name => $input){
			if(isset($data[$input_name])){
				$text .= '##' . $input_name . ' (' . sizeof($data[$input_name]) . ')</br>';

				$total = array();
				foreach ($data[$input_name] as $activeid_pass => $user_answer){
					if(key_exists($user_answer,$total)){
						$total[$user_answer]++;
					}else{
						$total[$user_answer] = 1;
					}
				}
			}

			arsort($total);

			foreach ($total as $user_answer => $count){
				$text .= $count . ' (' . round(($count / sizeof($data[$input_name] ?? [])) * 100, 2) . '%); ' . $user_answer . '</br>';
			}
		}

		return $text;
	}
}