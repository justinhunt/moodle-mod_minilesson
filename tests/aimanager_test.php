<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_minilesson;

use core_ai\aiactions\generate_text;
use GuzzleHttp\Psr7\Response;

/**
 * Tests for the AI manager's Moodle AI provider path.
 *
 * Each core provider is given a mocked HTTP reply, so these tests check what MiniLesson sends and how
 * it reads the reply, without calling any AI service.
 *
 * @package    mod_minilesson
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_minilesson\aimanager
 * @covers     \mod_minilesson\local\aiprompts
 */
final class aimanager_test extends \advanced_testcase {
    /** @var array Grading instructions as the items pass them. */
    const INSTRUCTIONS = [
        'feedbackscheme' => 'Give two short tips.',
        'feedbacklanguage' => 'en-US',
        'markscheme' => 'Give one mark for each correct sentence.',
        'maxmarks' => 5,
    ];

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists(\core_ai\manager::class) || !class_exists(\core_ai\provider::class)) {
            $this->markTestSkipped('Core AI subsystem not available.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Create an enabled provider instance and send every kind of request to it.
     *
     * @param string $plugin openai, gemini or ollama
     * @return \core_ai\provider
     */
    private function use_provider(string $plugin): \core_ai\provider {
        $settings = [
            'openai' => ['model' => 'gpt-4o', 'endpoint' => 'https://api.openai.com/v1/chat/completions'],
            'gemini' => ['model' => 'gemini-2.0-flash',
                'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent'],
            'ollama' => ['model' => 'llama3.2'],
        ][$plugin];
        $settings['systeminstruction'] = 'Admin system instruction';
        $config = $plugin === 'ollama' ? ['endpoint' => 'http://localhost:11434'] : ['apikey' => '123'];

        $manager = \core\di::get(\core_ai\manager::class);
        $provider = $manager->create_provider_instance(
            classname: "\\aiprovider_{$plugin}\\provider",
            name: $plugin,
            enabled: true,
            config: $config,
            actionconfig: [generate_text::class => ['enabled' => true, 'settings' => $settings]],
        );
        foreach (array_keys(aimanager::AIMANAGER_ACTIONS) as $actiontype) {
            set_config('aiaction_' . $actiontype, $provider->id, constants::M_COMPONENT);
        }
        return $provider;
    }

    /**
     * A reply body in the shape the given provider's API returns.
     *
     * @param string $plugin
     * @param string $text what the model wrote
     * @return Response
     */
    private function reply(string $plugin, string $text): Response {
        switch ($plugin) {
            case 'openai':
                $body = [
                    'id' => 'chatcmpl-1',
                    'model' => 'gpt-4o',
                    'choices' => [['message' => ['role' => 'assistant', 'content' => $text], 'finish_reason' => 'stop']],
                    'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
                    'system_fingerprint' => 'fp_1',
                ];
                break;
            case 'gemini':
                $body = [
                    'responseId' => 'r1',
                    'candidates' => [['content' => ['parts' => [['text' => $text]]], 'finishReason' => 'STOP']],
                    'usageMetadata' => ['promptTokenCount' => 10, 'totalTokenCount' => 30],
                ];
                break;
            default:
                $body = [
                    'model' => 'llama3.2',
                    'response' => $text,
                    'done_reason' => 'stop',
                    'prompt_eval_count' => 10,
                    'eval_count' => 20,
                ];
        }
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($body));
    }

    public function test_extract_json_object(): void {
        $this->assertEquals('B1', aimanager::extract_json_object('{"cefr": "B1"}')->cefr);
        $fence = str_repeat(chr(96), 3);
        $this->assertEquals('B1', aimanager::extract_json_object("{$fence}json\n{\"cefr\": \"B1\"}\n{$fence}")->cefr);
        $this->assertEquals('B1', aimanager::extract_json_object("Here you go:\n{\"cefr\": \"B1\"}\nHope that helps.")->cefr);
        // Nested objects and braces inside strings survive.
        $nested = aimanager::extract_json_object('Sure! {"a": {"b": "x}y"}, "c": [1, 2]}');
        $this->assertEquals('x}y', $nested->a->b);
        $this->assertNull(aimanager::extract_json_object('The level is B1.'));
        $this->assertNull(aimanager::extract_json_object('[1, 2, 3]'));
        $this->assertNull(aimanager::extract_json_object(''));
    }

    public function test_cloudpoodll_is_default(): void {
        foreach (array_keys(aimanager::AIMANAGER_ACTIONS) as $actiontype) {
            $this->assertFalse(aimanager::uses_moodle_ai($actiontype));
        }
    }

    public function test_lessonbank_setting_only_on_lessonbank_site(): void {
        $options = aimanager::get_action_options();
        $this->assertArrayHasKey(aimanager::OPTION_GRADE_STUDENT_SUBMISSION, $options);
        $this->assertArrayHasKey(aimanager::OPTION_GENERATE_CODE, $options);
        $this->assertSame(aimanager::has_lessonbank(), isset($options[aimanager::OPTION_LESSONBANK_DETAILS]));
    }

    /**
     * Grading works with each core provider, and what is sent is the stock request: no provider
     * specific parameters are added and the admin's own settings are kept.
     *
     * @dataProvider provider_names
     * @param string $plugin
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_names')]
    public function test_autograde_text_each_provider(string $plugin): void {
        global $DB;
        $this->use_provider($plugin);
        $history = [];
        ['mock' => $mock] = $this->get_mocked_http_client($history);
        $fence = str_repeat(chr(96), 3);
        $reply = '{"correctedtext": "I went home.", "marks": 4, "feedback": ["Good.", "Use past tense."]}';
        $mock->append($this->reply($plugin, "{$fence}json\n{$reply}\n{$fence}"));

        $aimanager = new aimanager(\context_system::instance()->id, 'useast1', 'en-US');
        $response = $aimanager->autograde_text('I go home yesterday.', self::INSTRUCTIONS);

        $this->assertSame(0, $response->returnCode);
        $grade = json_decode($response->returnMessage);
        $this->assertEquals(4, $grade->marks);
        $this->assertEquals('I went home.', $grade->correctedtext);
        $this->assertEquals('I go home yesterday.', $grade->submittedtext);
        $this->assertEquals($plugin, $aimanager->get_last_provider());

        $this->assertCount(1, $history);
        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertArrayNotHasKey('response_format', $body);
        $this->assertArrayNotHasKey('max_tokens', $body);
        if ($plugin !== 'gemini') {
            // Core's Gemini provider always sends core's default system instruction, not the admin's.
            $this->assertStringContainsString('Admin system instruction', json_encode($body));
        }
        $this->assertStringContainsString('I go home yesterday.', json_encode($body));

        // Logged in the core AI action register like any other Moodle AI request.
        $this->assertEquals(1, $DB->count_records('ai_action_register', ['actionname' => 'generate_text', 'success' => 1]));
    }

    /**
     * Provider names for the data provider.
     *
     * @return array
     */
    public static function provider_names(): array {
        return [['openai'], ['gemini'], ['ollama']];
    }

    public function test_prose_reply_is_retried_once(): void {
        $this->use_provider('gemini');
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append($this->reply('gemini', 'The student wrote quite well, I would give 4 out of 5.'));
        $mock->append($this->reply('gemini', '{"correctedtext": "I went home.", "marks": 4, "feedback": null}'));

        $aimanager = new aimanager(\context_system::instance()->id, 'useast1', 'en-US');
        $response = $aimanager->autograde_text('I go home yesterday.', self::INSTRUCTIONS);
        $this->assertDebuggingCalled();

        $this->assertSame(0, $response->returnCode);
        $this->assertEquals(4, json_decode($response->returnMessage)->marks);
    }

    public function test_prose_reply_twice_is_an_error_not_an_empty_grade(): void {
        $this->use_provider('ollama');
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append($this->reply('ollama', 'Good effort!'));
        $mock->append($this->reply('ollama', 'Really, good effort!'));

        $aimanager = new aimanager(\context_system::instance()->id, 'useast1', 'en-US');
        $response = $aimanager->autograde_text('I go home yesterday.', self::INSTRUCTIONS);
        // Two "not the JSON asked for" messages, then the failure.
        $this->assertDebuggingCalledCount(3);

        $this->assertSame(1, $response->returnCode);
        $this->assertEquals(get_string('aiprovider_badreply', constants::M_COMPONENT), $response->returnMessage);
    }

    public function test_disabled_provider_fails_cleanly(): void {
        $provider = $this->use_provider('openai');
        $manager = \core\di::get(\core_ai\manager::class);
        $manager->disable_provider_instance($provider);

        $aimanager = new aimanager(\context_system::instance()->id, 'useast1', 'en-US');
        $response = $aimanager->predict_cefr('Some text.');
        $this->assertDebuggingCalled();

        $this->assertSame(1, $response->returnCode);
        $this->assertEquals(get_string('aiprovider_unavailable', constants::M_COMPONENT), $response->returnMessage);
    }

    public function test_provider_error_is_not_retried(): void {
        $this->use_provider('openai');
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append(new Response(
            401,
            ['Content-Type' => 'application/json'],
            json_encode(['error' => ['message' => 'Invalid API key']])
        ));

        $aimanager = new aimanager(\context_system::instance()->id, 'useast1', 'en-US');
        $response = $aimanager->count_unique_ideas('Some text.');
        $this->assertDebuggingCalled();

        $this->assertSame(1, $response->returnCode);
        $this->assertCount(0, $mock);
    }

    public function test_generate_code_returns_plain_text(): void {
        $this->use_provider('openai');
        ['mock' => $mock] = $this->get_mocked_http_client();
        $code = "function add(a, b) {\n    return a + b;\n}";
        $mock->append($this->reply('openai', $code));

        $aimanager = new aimanager(\context_system::instance()->id, 'useast1', 'en-US');
        $this->assertEquals($code, $aimanager->generate_code('Write an add function.'));
    }

    public function test_analysis_replies_keep_cloudpoodll_shape(): void {
        $this->use_provider('openai');
        ['mock' => $mock] = $this->get_mocked_http_client();
        $mock->append($this->reply('openai', '{"cefr": "b2"}'));
        $mock->append($this->reply('openai', '{"count": 3}'));
        $mock->append($this->reply('openai', '{"relevance": 8}'));
        $mock->append($this->reply('openai', '{"correctedtext": "I went home."}'));

        $aimanager = new aimanager(\context_system::instance()->id, 'useast1', 'en-US');
        $this->assertSame('B2', $aimanager->predict_cefr('Some text.')->returnMessage);
        $this->assertSame(3, $aimanager->count_unique_ideas('Some text.')->returnMessage);
        $this->assertEqualsWithDelta(0.8, $aimanager->get_topic_relevance('Holidays', 'I went home.')->returnMessage, 0.001);
        $this->assertSame('I went home.', $aimanager->request_grammar_correction('I go home.')->returnMessage);
    }
}
