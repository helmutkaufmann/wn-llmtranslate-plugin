# Mercator.LlmTranslate

Machine translation for [Winter.Translate](https://github.com/wintercms/wn-translate-plugin) through **any OpenAI-compatible or Anthropic-compatible API**: OpenAI, Anthropic, OpenRouter, and local servers such as **LM Studio**, **Ollama**, llama.cpp or vLLM.

It extends Winter.Translate without modifying it: the LLM endpoints appear next to Google and DeepL as translation methods.

## Features

- **Any LLM endpoint**: OpenAI-style (`/chat/completions`) or Anthropic-style (`/v1/messages`), with several named endpoints. No API key needed for local servers.
- **Manual translation**
  - The "copy from language" picker on multilingual fields lists your endpoints.
  - Messages screen (Settings → Translate messages): **Machine translate** button, for the selected language or all enabled languages.
  - Model fields: translate every record of a model, from the Review screen or with an artisan command.
- **Auto-translate on save** (opt-in): saving a model with changed default-language text queues a job that translates just those fields.
- **Review workflow**: every machine translation is recorded (provider, model, time, status). A review screen lets you filter, edit, approve and delete them.
- **Safe by design**: human-written and reviewed translations are never overwritten by auto-translate; HTML tags, URLs and placeholders (`:name`, `{{ var }}`, `%s`) are preserved; content is treated as data, never as instructions to the model.

## Requirements

- Winter CMS with the `Winter.Translate` plugin (v2.4+)
- PHP 8.2+
- An OpenAI- or Anthropic-compatible endpoint
- For auto-translate: a running queue worker (`php artisan queue:work`)

## Installation

Place the plugin in `plugins/mercator/llmtranslate`, then:

```bash
php artisan winter:up
```

## Configuration

Go to **Settings → Translate → Translation Providers** and open the **LLM** tab.

| Setting | Description |
| --- | --- |
| Endpoints | One or more named endpoints (see below). The name appears in the picker. |
| Extra instructions | Tone, formality or domain hints, added to every prompt. |
| Glossary | One `source = target` per line; the model is told to always use these terms. |
| Translatable models | Model classes (one per line) available for model-field translation. They must use the `TranslatableModel` behavior. |
| Auto-translate on save | Switch, provider and the models to auto-translate (must also be listed above). |

An endpoint has: **name**, **API format** (OpenAI or Anthropic), **base URL**, **model**, optional **API key** and **batch size** (strings per request; use a small value for small local models).

### Endpoint examples

| Service | Format | Base URL | API key |
| --- | --- | --- | --- |
| LM Studio | OpenAI | `http://localhost:1234/v1` | empty |
| Ollama | OpenAI | `http://localhost:11434/v1` | empty |
| OpenAI | OpenAI | `https://api.openai.com/v1` | required |
| Anthropic | Anthropic | `https://api.anthropic.com` | required |

For a local server, start it, load a model, and enter that model's identifier (LM Studio shows it in the server tab and at `/v1/models`).

When nothing is configured yet, a default local endpoint is used. Override its defaults in `.env`:

```
LLM_TRANSLATE_BASE_URL=http://localhost:11434/v1
LLM_TRANSLATE_MODEL=llama3.1
LLM_TRANSLATE_API_KEY=
```

Other defaults (batch size, timeout, maximum values translated per run, fields never translated such as `slug`) are in `config/config.php`.

## Usage

### UI messages

**Settings → Translate messages → Machine translate.** Choose a provider, tick *all enabled languages* or use the target language selected on the screen. Only messages that are empty in the target language are translated, unless you tick *Overwrite existing translations*. At most 200 messages are translated per language and run; run it again to continue.

### Model fields

**Settings → Translate → Review machine translations → Translate models**, or:

```bash
php artisan llmtranslate:models "Mercator\Courses\Models\Dimension" --to=de --to=fr
```

Options: `--provider=`, `--from=`, `--to=` (repeatable; default: all enabled languages), `--overwrite`, `--limit=`. The default language is never overwritten.

### Auto-translate on save

Enable it in the LLM tab, choose an endpoint and list the models. When a listed model is saved with changed default-language fields, a queued job translates them into all other enabled languages. Empty values and still-unreviewed machine translations are (re)written; **human and reviewed translations are left alone**. Failures are logged and never block saving.

### Review machine translations

**Settings → Translate → Review machine translations** lists every machine translation with its source text, the translation, and the provider and model used.

- Filter by status, type (UI messages / model fields), language and provider.
- **Approve** a translation, or edit it and **Save & approve**. Editing a machine translation anywhere (for example in the Messages screen or a model form) marks it reviewed automatically.
- **Delete** a translation (removes the target text, keeps the source), or **Delete all unreviewed** in the current filter. Reviewed translations are never touched by bulk delete.

## Permissions

| Permission | Allows |
| --- | --- |
| `mercator.llmtranslate.review` | Use the review screen |

Configuring endpoints uses Winter.Translate's `winter.translate.manage_settings`; the Messages screen uses `winter.translate.manage_messages`.

## Notes

- Text is sent to the configured endpoint. Use a local server if content must not leave your infrastructure.
- LLM output can contain mistakes. The review screen exists so a person can check machine translations before they count as reviewed.
- Winter.Translate hides providers without an API key. For keyless local servers the plugin stores a placeholder internally; you never need to enter one.

## License

[MIT](LICENSE) © 2026 Helmut Kaufmann <software@mercator.li>
