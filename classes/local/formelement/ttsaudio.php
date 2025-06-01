<?php

namespace mod_minilesson\local\formelement;

use mod_minilesson\constants;
use mod_minilesson\utils;
use MoodleQuickForm;
use MoodleQuickForm_group;

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/form/group.php');

class ttsaudio extends MoodleQuickForm_group {

    const ELNAME = 'ttsaudio';

    protected $_options = ['region' => null, 'langcode' => null];

    protected $langoptions;

    protected $voiceoptions;

    protected $selectedlang;

    protected $selectedvoice;

    public function __construct($elementName = null, $elementLabel = null, $options = [], $attributes = null) {
        parent::__construct($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_appendName = true;
        $this->_type = 'ttsaudio';
        $this->_options['region'] = get_config(constants::M_COMPONENT, 'awsregion');
        $this->_options['langcode'] = get_config(constants::M_COMPONENT, 'ttslanguage');
        $allregions = utils::get_region_options();
        $alllangs = utils::get_lang_options();
        if (isset($options['region']) && in_array($options['region'], array_keys($allregions))) {
            $this->_options['region'] = $options['region'];
        }
        if (isset($options['langcode']) && in_array($options['langcode'], array_keys($alllangs))) {
            $this->_options['langcode'] = $options['langcode'];
        }
        if (isset($options['formclass'])) {
            $this->_options['formclass'] = $options['formclass'];
        }
    }

    function _createElements() {
        $attributes = $this->getAttributes();
        if (is_null($attributes)) {
            $attributes = [];
        }
        switch($this->_options['region']) {
            case 'ningxia':
                $alllang = constants::ALL_VOICES_NINGXIA;
                break;
            default:
                $alllang = constants::ALL_VOICES;
                break;
        }
        $this->langoptions = array_reduce(array_keys($alllang), function($a, $langcode) {
            $a[$langcode] = get_string(strtolower($langcode), constants::M_COMPONENT);
            return $a;
        }, []);

        $this->voiceoptions = array_reduce(array_keys($alllang), function($a, $langcode) use ($alllang) {
            $inneroptions = [];
            foreach($alllang[$langcode] as $key => $option) {
                if (utils::can_speak_neural($key, $this->_options['region'])) {
                    $option .= ' (+)';
                }
                $inneroptions[$key] = $option;
            }
            $a[$langcode] = $inneroptions;
            return $a;
        }, []);

        $this->_elements = [];

        $langselect = $this->createFormElement('select', 'language',
                get_string('language', constants::M_COMPONENT), $this->langoptions, $attributes);
        $this->_elements[] = $langselect;

        $voiceoptions = array_key_exists($this->_options['langcode'], $this->voiceoptions) ?
            $this->voiceoptions[$this->_options['langcode']] : [];

        $voiceselect = $this->createFormElement('select', 'voice',
                get_string('voice', constants::M_COMPONENT), $voiceoptions, $attributes);
        $this->_elements[] = $voiceselect;
    }

    public function onQuickFormEvent($event, $arg, &$caller) {
        $this->setMoodleForm($caller);
        switch ($event) {
            case 'updateValue':
                $value = $this->_findValue($caller->_constantValues);
                if (null === $value) {
                    if ($caller->isSubmitted() && !$caller->is_new_repeat($this->getName())) {
                        $value = $this->_findValue($caller->_submitValues);
                    } else {
                        $value = $this->_findValue($caller->_defaultValues);
                    }
                }
                $finalvalue = null;
                if (null !== $value){
                    $this->_createElementsIfNotExist();
                    if (!is_array($value)) {
                        $value = ['voice' => $value];
                    }
                    if (!isset($value['language'])) {
                        foreach($this->voiceoptions as $langcode => $voicearr) {
                            foreach (array_keys($voicearr) as $k) {
                                if ($k === $value['voice']) {
                                    $finalvalue = ['language' => $langcode, 'voice' => $k];
                                    $this->_elements[1]->removeOptions();
                                    $this->_elements[1]->load($voicearr);
                                    $this->selectedlang = $langcode;
                                    $this->selectedvoice = $k;
                                }
                            }
                        }
                    } else if (array_key_exists($value['language'], $this->voiceoptions)) {
                        $finalvalue = $value;
                        $voiceoptions = $this->voiceoptions[$value['language']];
                        $this->_elements[1]->removeOptions();
                        $this->_elements[1]->load($voiceoptions);
                        $this->selectedlang = $value['language'];
                        if (isset($finalvalue['voice']) && array_key_exists($finalvalue['voice'], $voiceoptions)) {
                            $this->selectedvoice = $finalvalue['voice'];
                        }
                    }
                }
                if (null !== $finalvalue) {
                    $this->setValue($finalvalue);
                }
                break;
            case 'createElement':
                $caller->_defaultValues[$arg[0]] = ['language' => $this->_options['langcode']];
            default:
                return parent::onQuickFormEvent($event, $arg, $caller);
        }
    }

    public function exportValue(&$submitValues, $assoc = false) {
        $valuearray = [];
        foreach ($this->_elements as $element) {
            $thisexport = $element->exportValue($submitValues[$this->getName()], true);
            if (!is_null($thisexport)) {
                $valuearray += $thisexport;
            }
        }

        if (empty($valuearray) || !array_key_exists('voice', $valuearray)) {
            return null;
        }
        return $this->_prepareValue($valuearray['voice'], $assoc);
    }

    public function accept(&$renderer, $required = false, $error = null) {
        global $CFG, $OUTPUT;
        $this->_createElementsIfNotExist();

        $uniqid = random_string();
        $this->_generateId();
        $groupid = 'fgroup_' . $this->getAttribute('id')  . '_' . $uniqid;
        $this->updateAttributes(['id' => $groupid]);

        $advanced = isset($renderer->_advancedElements[$this->getName()]);
        $helpbutton = $this->getHelpButton();
        $label = $this->getLabel();
        $elementcontext = $this->export_for_template($OUTPUT);
        $elementcontext['wrapperid'] = $elementcontext['id'];
        $elementcontext['name'] = $elementcontext['groupname'];

        $context = [
            'element' => $elementcontext,
            'label' => $label,
            'required' => $required,
            'advanced' => $advanced,
            'helpbutton' => $helpbutton,
            'error' => $error,
            'wrapperoptions' => [
                [
                    'key' => 'region',
                    'value' => $this->_options['region']
                ],
                [
                    'key' => 'label',
                    'value' => $label
                ],
            ],
            'jsargs' => json_encode([
                'component' => constants::M_COMPONENT,
                'fragmentcallback' => 'ttsaudioelement',
                'elementid' => $elementcontext['wrapperid']
            ])
        ];
        if (in_array($this->getName(), $renderer->_stopFieldsetElements) && $renderer->_fieldsetsOpen > 0) {
            $renderer->_html .= $renderer->_closeFieldsetTemplate;
            $renderer->_fieldsetsOpen--;
        }

        if (!isset($this->selectedlang)) {
            $this->selectedlang = array_keys($this->langoptions)[0];
        }
        $voiceoptions = $this->voiceoptions[$this->selectedlang];
        if (!isset($this->selectedvoice)) {
            $this->selectedvoice = array_keys($voiceoptions)[0];
        }

        // first confirm we are authorised before we try to get the token
        $config = get_config(constants::M_COMPONENT);
        if(empty($config->apiuser) || empty($config->apisecret)){
            $errormessage = get_string('nocredentials', constants::M_COMPONENT,
                    $CFG->wwwroot . constants::M_PLUGINSETTINGS);
            // return error?
            $token = false;
        }else {
            // fetch token
            $token = utils::fetch_token($config->apiuser, $config->apisecret);

            // check token authenticated and no errors in it
            $errormessage = utils::fetch_token_error($token);
        }

        if (!$errormessage) {
            $btncontext = ['uniqid' => $uniqid];
            $btncontext['ttsautoplay'] = 0;
            $btncontext['ttsoption'] = 0;
            $btncontext['ttsaudio'] = constants::M_LANG_SAMPLES[$this->selectedlang];
            $btncontext['ttsaudiovoice'] = $this->selectedvoice;
            $btncontext['audiosrc'] = utils::fetch_polly_url($token, $this->_options['region'],
                $btncontext['ttsaudio'], $btncontext['ttsoption'], $btncontext['ttsaudiovoice']);
            $audiobtn = $OUTPUT->render_from_template(
                constants::M_COMPONENT . '/samplettsaudio', $btncontext);
            $context['element']['elements'][] = ['html' => $audiobtn];
        }

        $renderer->_html .= $OUTPUT->render_from_template(
            constants::M_COMPONENT . '/form/element-' . $this->getType(), $context);
        $renderer->finishGroup($this);
    }

    public static function register() {
        MoodleQuickForm::registerElementType(static::ELNAME, __FILE__, __CLASS__);
    }

}