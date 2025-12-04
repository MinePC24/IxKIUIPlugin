<?php
declare(strict_types=1);
/**
 *  This file is part of the AI Chat Repository Object plugin for ILIAS, which allows your platform's users
 *  To connect with an external LLM service
 *  This plugin is created and maintained by SURLABS.
 *
 *  The AI Chat Repository Object plugin for ILIAS is open-source and licensed under GPL-3.0.
 *  For license details, visit https://www.gnu.org/licenses/gpl-3.0.en.html.
 *
 *  To report bugs or participate in discussions, visit the Mantis system and filter by
 *  the category "AI Chat" at https://mantis.ilias.de.
 *
 *  More information and source code are available at:
 *  https://github.com/surlabs/AIChat
 *
 *  If you need support, please contact the maintainer of this software at:
 *  info@surlabs.es
 *
 */

namespace objects;

use ai\GWDG;
use ai\LLM;
use ai\OpenAI;
use ai\Ollama;
use DateTime;
use platform\IxKIUIPluginConfig;
use platform\IxKIUIPluginDatabase;
use platform\IxKIUIPluginException;

/**
 * Class AIChat
 * @authors Jesús Copado, Daniel Cazalla, Saúl Díaz, Juan Aguilar <info@surlabs.es>
 */
class IxKIUIPlugin
{
    private int $id = 0;
    private bool $online = false;
    private string $prompt = "";
    private string $disclaimer = "";
    private int $max_memory_messages = 0;
    private int $characters_limit = 0;
    private string $openai_model = "";
    private string $openai_api_key = "";
    private bool $openai_streaming = false;
    private string $ollama_model = "";
    private string $service_to_use = "";
    private string $gwdg_model = "";
    private bool $gwdg_streaming = false;
    private ?LLM $llm = null;

    /**
     * @throws IxKIUIPluginException
     */
    public function __construct(?int $id = null)
    {
        if ($id !== null && $id > 0) {
            $this->id = $id;

            $this->loadFromDB();
        }

        $this->loadLLM();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function isOnline(): bool
    {
        return $this->online;
    }

    public function setOnline(bool $online): void
    {
        $this->online = $online;
    }

    /**
     * @throws IxKIUIPluginException
     */
    public function getPrompt(bool $strict = false): string
    {
        if ($this->prompt != "" || $strict) {
            return $this->prompt;
        }

        return IxKIUIPluginConfig::get("prompt");
    }

    public function setPrompt(string $prompt): void
    {
        $this->prompt = $prompt;
    }

    /**
     * @throws IxKIUIPluginException
     */
    public function getDisclaimer(bool $strict = false): string
    {
        if ($this->disclaimer != "" || $strict) {
            return $this->disclaimer;
        }

        return IxKIUIPluginConfig::get("disclaimer");
    }

    public function setDisclaimer(string $disclaimer): void
    {
        $this->disclaimer = $disclaimer;
    }

    /**
     * @throws IxKIUIPluginException
     */
    public function getMaxMemoryMessages(bool $strict = false): int
    {
        if ($this->max_memory_messages != 0 || $strict) {
            return $this->max_memory_messages;
        }

        if (!empty(IxKIUIPluginConfig::get("max_memory_messages"))) {
            return IxKIUIPluginConfig::get("max_memory_messages");
        }

        return 100;
    }

    public function setMaxMemoryMessages(int $max_memory_messages): void
    {
        $this->max_memory_messages = $max_memory_messages;
    }

    /**
     * @throws IxKIUIPluginException
     */
    public function getCharactersLimit(bool $strict = false): int
    {
        if ($this->characters_limit != 0 || $strict) {
            return $this->characters_limit;
        }

        if (!empty(IxKIUIPluginConfig::get("characters_limit"))) {
            return IxKIUIPluginConfig::get("characters_limit");
        }

        return 2000;
    }

    public function setCharactersLimit(int $characters_limit): void
    {
        $this->characters_limit = $characters_limit;
    }

    /**
     * @throws IxKIUIPluginException
     */
    public function getOpenaiModel(bool $strict = false): string
    {
        if ($this->openai_model != "" || $strict) {
            return $this->openai_model;
        }

        return IxKIUIPluginConfig::get("openai_model");
    }

    public function setOpenaiModel(string $openai_model): void
    {
        $this->openai_model = $openai_model;
    }

    /**
     * @throws IxKIUIPluginException
     */
    public function getOpenaiApiKey(bool $strict = false): string
    {
        if ($this->openai_api_key != "" || $strict) {
            return $this->openai_api_key;
        }

        return IxKIUIPluginConfig::get("openai_api_key");
    }

    public function setOpenaiApiKey(string $openai_api_key): void
    {
        $this->openai_api_key = $openai_api_key;
    }

    /**
     * @throws IxKIUIPluginException
     */
    public function isOpenaiStreaming(bool $strict = false): bool
    {
        if ($this->getServiceToUse() != "openai") {
            return false;
        }

        if ($this->openai_streaming || $strict) {
            return $this->openai_streaming;
        }

        return IxKIUIPluginConfig::get("openai_streaming") == "1";
    }

    public function setOpenaiStreaming(bool $openai_streaming): void
    {
        $this->openai_streaming = $openai_streaming;
    }

    /**
     * @throws IxKIUIPluginException
     */
    public function getOllamaModel(bool $strict = false): string
    {
        if ($this->ollama_model != "" || $strict) {
            return $this->ollama_model;
        }

        return IxKIUIPluginConfig::get("ollama_model");
    }

    public function setOllamaModel(string $ollama_model): void
    {
        $this->ollama_model = $ollama_model;
    }

    /**
     * @throws IxKIUIPluginException
     */
    public function getOllamaModelsList(): array
    {
        if (!empty(IxKIUIPluginConfig::get("ollama_models"))) {
            return IxKIUIPluginConfig::get("ollama_models");
        }

        return [];
    }

    public function getServiceToUse(bool $strict = false): string
    {
        $available_services = IxKIUIPluginConfig::get("available_services");

        if (($this->service_to_use != "" && isset($available_services[$this->service_to_use]) && $available_services[$this->service_to_use]) || $strict) {
            return $this->service_to_use;
        }

        foreach ($available_services as $service => $available) {
            if ($available) {
                return $service;
            }
        }

        return "";
    }

    public function setServiceToUse(string $service_to_use): void
    {
        $this->service_to_use = $service_to_use;
    }

    public function getGwdgModel(bool $strict = false): string
    {
        if ($this->gwdg_model != "" || $strict) {
            return $this->gwdg_model;
        }

        return IxKIUIPluginConfig::get("gwdg_model");
    }

    public function setGwdgModel(string $gwdg_model): void
    {
        $this->gwdg_model = $gwdg_model;
    }

    public function isGwdgStreaming(bool $strict = false): bool
    {
        if ($this->getServiceToUse() != "gwdg") {
            return false;
        }

        if ($this->gwdg_streaming || $strict) {
            return $this->gwdg_streaming;
        }

        return IxKIUIPluginConfig::get("gwdg_streaming") == "1";
    }

    public function setGwdgStreaming(bool $gwdg_streaming): void
    {
        $this->gwdg_streaming = $gwdg_streaming;
    }

    public function getGwdgModelsList(): array
    {
        if (!empty(IxKIUIPluginConfig::get("gwdg_models"))) {
            return IxKIUIPluginConfig::get("gwdg_models");
        }

        return [];
    }

    public function getLlm(): ?LLM
    {
        return $this->llm;
    }

    public function setLlm(?LLM $llm = null): void
    {
        $this->llm = $llm;
    }

    /**
     * @throws IxKIUIPluginException
     */
    public function loadFromDB(): void
    {
        $database = new IxKIUIPluginDatabase();

        $result = $database->select("xaid_objects", ["id" => $this->getId()]);

        if (isset($result[0])) {
            $this->setOnline((bool) $result[0]["online"]);
            $this->setPrompt((string) $result[0]["prompt"]);
            $this->setDisclaimer((string) $result[0]["disclaimer"]);
            $this->setMaxMemoryMessages((int) $result[0]["max_memory_messages"]);
            $this->setCharactersLimit((int) $result[0]["characters_limit"]);
            $this->setOpenaiModel((string) $result[0]["openai_model"]);
            $this->setOpenaiApiKey((string) $result[0]["openai_api_key"]);
            $this->setOpenaiStreaming((bool) $result[0]["openai_streaming"]);
            $this->setOllamaModel((string) $result[0]["ollama_model"]);
            $this->setServiceToUse($result[0]["service_to_use"]);
            $this->setGwdgModel((string) $result[0]["gwdg_model"]);
            $this->setGwdgStreaming((bool) $result[0]["gwdg_streaming"]);
        }
    }

    /**
     * @throws IxKIUIPluginException
     */
    public function save(): void
    {
        if (!isset($this->id) || $this->id == 0) {
            throw new IxKIUIPluginException("AIChat::save() - AIChat ID is 0");
        }

        $database = new IxKIUIPluginDatabase();

        $database->insertOnDuplicatedKey("xaid_objects", array(
            "id" => $this->id,
            "online" => (int) $this->online,
            "prompt" => $this->prompt,
            "disclaimer" => $this->disclaimer,
            "max_memory_messages" => $this->max_memory_messages,
            "characters_limit" => $this->characters_limit,
            "openai_model" => $this->openai_model,
            "openai_api_key" => $this->openai_api_key,
            "openai_streaming" => (int) $this->openai_streaming,
            "ollama_model" => $this->ollama_model,
            "service_to_use" => $this->service_to_use,
            "gwdg_model" => $this->gwdg_model,
            "gwdg_streaming" => (int) $this->gwdg_streaming,
));
    }

    /**
     * @throws IxKIUIPluginException
     */
    public function delete(): void
    {
        $database = new IxKIUIPluginDatabase();

        $database->delete("xaid_objects", ["id" => $this->id]);

        $chats = $database->select("xaid_chats", ["obj_id" => $this->id]);

        foreach ($chats as $chat) {
            $chat_obj = new Chat($chat["id"]);

            $chat_obj->delete();
        }
    }

    /**
     * @throws IxKIUIPluginException
     */
    public function getChatsForApi(?int $user_id = null): array
    {
        $database = new IxKIUIPluginDatabase();

        $where = [
            "obj_id" => $this->getId(),
        ];

        if (isset($user_id) && $user_id > 0) {
            $where["user_id"] = $user_id;
        }

        $chats = $database->select("xaid_chats", $where, null, "ORDER BY last_update DESC");

        if (empty($chats) && isset($user_id) && $user_id > 0) {
            $chat = new Chat();

            $chat->setMaxMessages($this->getMaxMemoryMessages());

            $chat->setObjId($this->getId());
            $chat->setUserId($user_id);

            $chat->save();

            return $this->getChatsForApi($user_id);
        }

        return $chats;
    }

    /**
     * @throws IxKIUIPluginException
     */
    private function loadLLM()
    {
        $service_to_use = $this->getServiceToUse();

        if (!empty($service_to_use)) {
            switch ($service_to_use) {
                case "openai":
                    $this->llm = new OpenAI($this->getOpenaiModel());
                    $this->llm->setApiKey($this->getOpenaiApiKey());
                    $this->llm->setMaxMemoryMessages($this->getMaxMemoryMessages());
                    $this->llm->setPrompt($this->getPrompt());
                    $this->llm->setStreaming($this->isOpenaiStreaming());
                    break;
                case "ollama":
                    $models = $this->getOllamaModelsList();
                    $model = $this->getOllamaModel();

                    if (in_array($model, $models)) {
                        $this->llm = new Ollama($model);
                        $this->llm->setEndpoint(IxKIUIPluginConfig::get("ollama_endpoint"));
                        $this->llm->setMaxMemoryMessages($this->getMaxMemoryMessages());
                        $this->llm->setPrompt($this->getPrompt());
                    }
                    break;
                case "gwdg":
                    $models = $this->getGwdgModelsList();
                    $model = $this->getGwdgModel();

                    if (in_array($model, $models) || array_key_exists($model, $models)) {
                        $this->llm = new GWDG($model);
                        $this->llm->setApiKey(IxKIUIPluginConfig::get("gwdg_api_key"));
                        $this->llm->setMaxMemoryMessages($this->getMaxMemoryMessages());
                        $this->llm->setPrompt($this->getPrompt());
                        $this->llm->setStreaming($this->isGwdgStreaming());
                    }
                    break;
                default:
                    throw new IxKIUIPluginException("AIChat::loadLLM() - LLM service to use not valid (Service: " . $service_to_use . ")");
            }
        }
    }
    
    /**
     * @throws IxKIUIPluginException
     */
    public function getLLMResponse(Chat $chat): Message
    {
        $llm_response = $this->llm->sendChat($chat);

        $response = new Message();

        $response->setChatId($chat->getId());
        $response->setDate(new DateTime());
        $response->setRole("assistant");
        $response->setMessage($llm_response);

        $response->save();

        return $response;
    }
}