<?php
// converter.php

session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 660);

// --- OPENROUTER CONFIGURATION ---
define('DEFAULT_OPENROUTER_API_KEY', ''); // DEFAULT KEY
define('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1');
define('YOUR_SITE_URL', 'https://my-site.com'); // Replace with your actual site URL
define('YOUR_SITE_NAME', 'Latex Format Converter');

$OPENROUTER_API_KEY = $_SESSION['user_api_key'] ?? DEFAULT_OPENROUTER_API_KEY;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_api_key') {
    header('Content-Type: application/json');
    if (isset($_POST['new_api_key'])) {
        $_SESSION['user_api_key'] = trim($_POST['new_api_key']);
        echo json_encode(['success' => true, 'message' => 'API Key saved. It will be used for the next request.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No API Key provided.']);
    }
    exit;
}

$baseConfig = [
    'max_tokens'     => 60000,
    'temperature'    => 0.2,
];

$availableModels = [ // Keep your model list
    'Google (via OpenRouter)' => [
         'google/gemini-2.0-flash-001' => [
              'display' => 'gemini-2.0-flash',
               'recommended' => true
         ],
         'google/gemini-2.5-flash' => [
              'display' => 'gemini-2.5-flash',
               'recommended' => false
         ]
    ],
     'Anthropic (via OpenRouter)' => [
        'anthropic/claude-sonnet-4' => [
            'display' => 'claude-sonnet-4',
            'recommended' => false,
        ]
    ],
     'openai (via OpenRouter)' => [
        'openai/gpt-4o-mini' => [
            'display' => 'gpt-4o-mini',
            'recommended' => false,
        ]
    ]
 ];
$targetFormatToDir = [ // Format list
    'IEEEtran Conference Format' => 'ieee_conference',
    'IEEEtran Journal Format'    => 'ieee_journal',
    'IEEE Access Format'         => 'ieee_access',
    'MDPI'                       => 'mdpi',
    'Springer LNCS Conference Format (llncs)' => 'springer_llncs',
    'Elsevier Article - Review (elsarticle review)' => 'elsevier_elsarticle_review',
];

function remove_latex_comments($text) {
    $lines = explode("\n", $text);
    $cleaned_lines = [];
    foreach ($lines as $line) {
        if (preg_match('/(?<!\\\\)%/', $line, $matches, PREG_OFFSET_CAPTURE)) {
            $cleaned_lines[] = substr($line, 0, $matches[0][1]);
        } else {
            $cleaned_lines[] = $line;
        }
    }
    return implode("\n", $cleaned_lines);
}

$errorMessage = '';
$inputText    = $_POST['paper_text'] ?? ($_SESSION['form_input_text'] ?? '');
$targetFormat  = $_POST['format_choice'] ?? ($_SESSION['form_target_format'] ?? '');
$selectedModel = $_POST['model_choice'] ?? ($_SESSION['form_selected_model'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_SESSION['form_error_message_display'])) {
    unset($_SESSION['form_input_text'], $_SESSION['form_target_format'], $_SESSION['form_selected_model']);
}
if (isset($_SESSION['form_error_message_display'])) {
    $errorMessage = $_SESSION['form_error_message_display'];
    unset($_SESSION['form_error_message_display']);
}

$clsMap = [ 
    'IEEE' => [
        'IEEEtran Conference Format' => 'IEEEtran.cls (conference option)',
        'IEEEtran Journal Format'    => 'IEEEtran.cls (journal option)',
        'IEEE Access Format'         => 'ieeeaccess.cls',
    ],
    'MDPI' => [
        'MDPI' => 'mdpi.cls (generic template, uses Definitions/mdpi.cls)',
    ],
    'Springer' => [
        'Springer LNCS Conference Format (llncs)'       => 'llncs.cls',
    ],
    'Elsevier' => [
        'Elsevier Article - Review (elsarticle review)'  => 'elsarticle.cls (review option)',
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] !== 'save_api_key')) {
    $selectedModel = $_POST['model_choice'] ?? '';
    $inputText     = $_POST['paper_text'] ?? '';
    if (!empty($_FILES['paper_file']['tmp_name']) && $_FILES['paper_file']['error'] === UPLOAD_ERR_OK) {
        $inputText = file_get_contents($_FILES['paper_file']['tmp_name']);
    }
    $targetFormat  = $_POST['format_choice'] ?? '';
    $inputText = remove_latex_comments($inputText);

    $_SESSION['form_input_text'] = $inputText;
    $_SESSION['form_target_format'] = $targetFormat;
    $_SESSION['form_selected_model'] = $selectedModel;

    $isValidFormat = false;
    if (!empty($targetFormat)) {
        foreach ($clsMap as $formats) {
            if (array_key_exists($targetFormat, $formats)) {
                $isValidFormat = true;
                break;
            }
        }
    }
    $isValidModel = false;
    if (!empty($selectedModel)) {
       foreach ($availableModels as $models) {
           if (array_key_exists($selectedModel, $models)) {
               $isValidModel = true;
               break;
           }
       }
    }

    if (empty($selectedModel)) {
        $errorMessage = 'Please select an AI Model.';
    } elseif (!$isValidModel) {
        $errorMessage = 'Invalid AI Model selected: ' . htmlspecialchars($selectedModel);
    } elseif (empty($targetFormat)) {
        $errorMessage = 'Please select a target format.';
    } elseif (!$isValidFormat) {
        $errorMessage = 'Unsupported target format: ' . htmlspecialchars($targetFormat);
    } elseif (empty(trim($inputText))) {
        $errorMessage = 'Please paste text or upload a file.';
    } else {
        try {
            $currentConfig = $baseConfig;
            $referenceTemplateContent = '';
            $templateDirName = $targetFormatToDir[$targetFormat] ?? null;
            if ($templateDirName) {
                $pathToTemplateTex = __DIR__ . '/latex_templates/' . $templateDirName . '/template.tex';
                if (file_exists($pathToTemplateTex) && is_readable($pathToTemplateTex)) {
                    $referenceTemplateContent = file_get_contents($pathToTemplateTex);
                }
            }
            $convertedLatex = convertToFormat($inputText, $selectedModel, $currentConfig, $targetFormat, $referenceTemplateContent, $OPENROUTER_API_KEY);
            if (empty(trim($convertedLatex))) {
                $errorMessage = "API returned an empty result. Model: " . htmlspecialchars($selectedModel) . ". Check API settings or prompt.";
            } else {
                $_SESSION['converted_latex'] = $convertedLatex;
                $_SESSION['target_format_for_download'] = $targetFormat;
                $_SESSION['original_input_text_for_result'] = $inputText;
                $_SESSION['selected_model_for_result'] = $selectedModel;
                unset($_SESSION['form_input_text'], $_SESSION['form_target_format'], $_SESSION['form_selected_model']);
                header('Location: result_display.php');
                exit;
            }
        } catch (Exception $e) {
            $errorMessage = 'Conversion error: ' . $e->getMessage();
        }
    }
    if (!empty($errorMessage)) {
        $_SESSION['form_error_message_display'] = $errorMessage;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

function convertToFormat($userContent, $openRouterModelSlug, $config, $targetFormat, $referenceTemplateContent = '', $apiKey) {
    $baseInstructionsForUserText = "You are an expert LaTeX formatter.
Your task is to convert the following user-provided text into a complete, compilable LaTeX document suitable for the **" . htmlspecialchars($targetFormat) . "** academic format.
First, thoroughly analyze the user's text to identify its structure: title, authors, affiliations, abstract, keywords, main sections (e.g., Introduction, Methods, Results, Conclusion), and any references.
Preserve all original content from the user's text. Do not summarize, paraphrase, or add new information.
If the user's text contains LaTeX (e.g., equations, tables), preserve these elements accurately.
If references are listed, format them as `\bibitem{}` entries within a `thebibliography` environment.

The user-provided text content to process is below this line:
---------------------------------------------------\n" . $userContent . "\n---------------------------------------------------\n\n";

    $prompt = "";
    $finalOutputInstruction = "Output Only LaTeX Code: The final output must be only the generated LaTeX code for the new document, starting with `\documentclass` and ending with `\end{document}`. Do not generate any comments in the LaTeX code. No extra commentary.";


    if (!empty($referenceTemplateContent)) {
        $prompt .= "You are provided with an **example LaTeX template file** (content below) for the **" . htmlspecialchars($targetFormat) . "** format.
You should **NOT directly fill or modify this example template**.
Instead, **use it as a detailed reference and guide** to understand the typical structure, `\documentclass` used, required packages, specific commands for metadata (title, authors, abstract, keywords, affiliations), sectioning, and bibliography style for the **" . htmlspecialchars($targetFormat) . "** format.\n\n";
        $prompt .= "--- EXAMPLE TEMPLATE START ---\n" . $referenceTemplateContent . "\n--- EXAMPLE TEMPLATE END ---\n\n";
        $prompt .= "**Your Task:**\n";
        $prompt .= "1.  **Analyze the Example Template:** Based on the example, determine the correct `\documentclass` (e.g., `mdpi`, `elsarticle`, `IEEEtran`, `llncs`, `svjour3`, `acmart`) and its typical options (e.g., `journal`, `article`, `submit`, `conference`, `review`). Identify standard LaTeX packages commonly used with this format (e.g., `amsmath`, `graphicx`, `cite`, `inputenc`, `babel`, `booktabs`, `hyperref`). Also, note any specific environments or commands for metadata (title, authors, affiliations, abstract, keywords), sectioning, figures, tables, and bibliography.\n";
        $prompt .= "2.  **Generate a NEW, COMPLETE LaTeX Document:** Using the insights from the example template and your LaTeX expertise, create a *new and complete* LaTeX document from scratch that incorporates the user's text content.\n";
        $prompt .= "3.  **Mimic the Style and Structure:** The generated document should mimic the style, structure, and essential commands observed in the example template for the **" . htmlspecialchars($targetFormat) . "** format. For instance, if the template uses specific commands like `\Title{}`, `\Author{}`, `\Address{}`, `\keyword{}`, use those. If it uses standard commands like `\\title{}`, `\\author{}`, `\\section{}`, use those.\n";
        $prompt .= "4.  **Incorporate User Content:** Map the identified parts of the user's text (title, authors, abstract, sections, etc.) to the appropriate LaTeX commands and structure for the target format.\n";
        $prompt .= "5.  **Preamble:** Construct a suitable preamble, including the identified `\documentclass` and necessary packages.\n";
        $prompt .= "6.  **No Direct Copying of Template Text (unless it's a command):** Do not copy large chunks of explanatory text or placeholder content from the example template. Only use the structural elements and commands as a guide.\n";
        $prompt .= "7.  **" . $finalOutputInstruction . "**\n\n";
        $prompt .= $baseInstructionsForUserText;
    } else {
        $prompt .= "You are an expert LaTeX formatter. Your task is to convert the user-provided text (details below) into a complete, compilable LaTeX document for the **" . htmlspecialchars($targetFormat) . "** format.\n";
        $prompt .= "Since no specific example template is provided for this format, create the document from scratch using your knowledge of standard practices for the **" . htmlspecialchars($targetFormat) . "** format.\n";
        $prompt .= "This includes selecting the appropriate `\documentclass` and its options, and including a standard preamble (e.g., `amsmath`, `graphicx`, `cite` if typically used).\n";
        $prompt .= "Ensure you correctly structure the title, authors, abstract, keywords, main sections, and references using standard LaTeX commands suitable for the **" . htmlspecialchars($targetFormat) . "** format.\n";
        $prompt .= "Pay attention to where `\maketitle` should be placed if required by the format.\n\n";
        $prompt .= $finalOutputInstruction . "\n\n";
        $prompt .= $baseInstructionsForUserText;
    }


    $requestBody = [
        'model' => $openRouterModelSlug,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => (float)$config['temperature'],
        'max_tokens' => (int)$config['max_tokens'],
    ];

    $jsonData = json_encode($requestBody);
    if ($jsonData === false) throw new Exception('Failed to encode JSON: ' . json_last_error_msg());

    $url = OPENROUTER_BASE_URL . '/chat/completions';
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $apiKey,
        'HTTP-Referer: ' . YOUR_SITE_URL,
        'X-Title: ' . YOUR_SITE_NAME
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_TIMEOUT => 660,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($ch) ? curl_error($ch) : null;
    curl_close($ch);

    if ($curlError) throw new Exception('cURL Error: ' . $curlError);
    $data = json_decode($response, true);

    if ($httpCode !== 200 || $data === null) {
        $apiErrorMessage = 'Unknown API error.';
        if (isset($data['error']['message'])) {
            $apiErrorMessage = $data['error']['message'];
        } elseif (is_string($response) && strlen($response) < 500) {
            $apiErrorMessage = htmlspecialchars($response);
        }
        throw new Exception('API request failed. HTTP Code: ' . $httpCode . '. Error: ' . $apiErrorMessage);
    }
    if (isset($data['error'])) {
        throw new Exception('API Error: ' . ($data['error']['message'] ?? 'Unknown API error') . '. Type: ' . ($data['error']['type'] ?? 'N/A'));
    }
    if (isset($data['choices'][0]['message']['content'])) {
        $fullResponse = $data['choices'][0]['message']['content'];
        $generatedLatex = '';
        if (preg_match('/```(?:latex)?\s*(.*?)\s*```/is', $fullResponse, $matches)) {
            $generatedLatex = $matches[1];
        } else {
            $generatedLatex = $fullResponse;
        }
        return trim($generatedLatex);
    }
    throw new Exception('Generated text not found in API response. Response: ' . htmlspecialchars(substr($response, 0, 1000)));
}


$current_api_key_display = "Not Set or Using Default";
if (!empty($OPENROUTER_API_KEY) && $OPENROUTER_API_KEY !== DEFAULT_OPENROUTER_API_KEY) {
    $current_api_key_display = "Custom: " . substr($OPENROUTER_API_KEY, 0, 7) . str_repeat('*', max(0, strlen($OPENROUTER_API_KEY) - 10)) . substr($OPENROUTER_API_KEY, -3);
} elseif (!empty(DEFAULT_OPENROUTER_API_KEY)) {
     $current_api_key_display = "Default: " . substr(DEFAULT_OPENROUTER_API_KEY, 0, 7) . str_repeat('*', max(0, strlen(DEFAULT_OPENROUTER_API_KEY) - 10)) . substr(DEFAULT_OPENROUTER_API_KEY, -3);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Latex Format Converter (OpenRouter API)</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body { font-family: Arial, sans-serif; background: #f5f5f5; margin:0; padding:20px; color: #333; }
    .container { max-width:800px; margin:auto; background:#fff; padding:20px 30px; border-radius:8px; box-shadow:0 2px 15px rgba(0,0,0,.1);}
    .header-bar {
        display: flex;
        justify-content: center; 
        align-items: center;
        margin-bottom: 20px;
        position: relative; 
    }
    .header-bar h1 {
        color: #4a4a4a;
        margin: 0; 
        font-size: 1.8em; 
    }
    h2 { text-align:left; color: #5a5a5a; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top:25px; margin-bottom:15px; font-size: 1.3em;}
    .step { margin:20px 0; padding:20px; background:#f9f9f9; border: 1px solid #e0e0e0; border-radius:5px; }
    label { display:block; margin-top:15px; margin-bottom: 5px; font-weight:bold; color: #555;}
    select, textarea, input[type=file], input[type=password], button {
        width:100%;
        padding:10px;
        margin-top:5px;
        border:1px solid #ccc;
        border-radius:4px;
        box-sizing: border-box;
        font-size: 1em;
    }
    textarea { height:200px; resize:vertical; }
    input[type=file] { padding: 7px; }
    optgroup { font-weight: bold; font-style: italic;}
    .error {
        color: #D8000C;
        background-color: #FFD2D2;
        border: 1px solid #D8000C;
        padding: 10px;
        margin: 20px 0;
        text-align:left;
        border-radius: 4px;
        white-space: pre-wrap;
        word-wrap: break-word;
    }
    button {
        background:#007bff; color:#fff; border:none; margin-top:25px; cursor:pointer;
        padding:12px 15px; font-size: 1.1em; transition: background-color 0.2s ease-in-out;
    }
    button:hover { background: #0056b3; }
    #overlay {
      display: none; position: fixed; top: 0; left: 0;
      width: 100%; height: 100%; background: rgba(255,255,255,0.8);
      z-index: 9999; justify-content: center; align-items: center;
      flex-direction: column; color: #333; font-size: 1.2em; text-align: center;
    }
    .spinner {
      border: 8px solid #e0e0e0; border-top: 8px solid #007bff;
      border-radius: 50%; width: 60px; height: 60px;
      animation: spin 1s linear infinite; margin-bottom: 20px;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

    .settings-container {
        position: absolute; 
        top: 50%;
        right: 0px; 
        transform: translateY(-50%);
    }
    #settingsGearButton {
        background: none;
        border: none;
        cursor: pointer;
        padding: 8px; 
        font-size: 22px; 
        color: #555;
        line-height: 1;
    }
    #settingsGearButton:hover {
        color: #007bff;
    }
    .api-key-dropdown {
        display: none;
        position: absolute;
        top: calc(100% + 5px); 
        right: 0;
        background-color: #fff;
        min-width: 320px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        z-index: 10000; 
        padding: 15px 20px;
        border-radius: 5px;
        border: 1px solid #ddd;
    }
    .api-key-dropdown label { margin-top: 0; }
    .api-key-dropdown input[type="password"] { margin-bottom: 10px; }
    .api-key-dropdown button { margin-top: 10px; font-size: 0.9em; padding: 8px 12px; }
    #apiKeyStatusDropdown { font-size: 0.8em; color: #666; margin-top: 5px; margin-bottom: 10px; }
    #apiKeyMessageDropdown { font-size:0.9em; margin-top:10px; }
  </style>
</head>
<body>
  <div id="overlay">
    <div class="spinner"></div>
    <p>Converting, please wait...<br>This might take a moment.</p>
  </div>

  <div class="container">
    <div class="header-bar">
        <h1>Latex Format Converter</h1>
        <div class="settings-container">
            <button type="button" id="settingsGearButton" title="API Key Settings">
                <i class="fas fa-cog"></i> 
            </button>
            <div id="apiKeyDropdown" class="api-key-dropdown">
                <label for="new_api_key_input_dropdown">OpenRouter API Key:</label>
                <input type="password" id="new_api_key_input_dropdown" placeholder="Enter new API Key (sk-or-v1-...)">
                <div id="apiKeyStatusDropdown">Current: <?= htmlspecialchars($current_api_key_display) ?></div>
                <button type="button" id="saveApiKeyButtonDropdown">Save Key</button>
                <p id="apiKeyMessageDropdown"></p>
            </div>
        </div>
    </div>
    
    
    <?php if ($errorMessage): ?>
        <div class="error">
            <strong>Error:</strong><br><?= nl2br(htmlspecialchars($errorMessage)) ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" id="converterForm">
    <div class="step">
     <h2>Introduction</h2>
     <p>
    This tool allows you to automatically convert academic content into properly structured LaTeX documents
    compatible with popular journal and conference formats. It leverages the power of <strong>Large Language Models via OpenRouter</strong>
    to interpret plain text and generate compilable, publication-ready LaTeX code.
  </p>
  <p>
    Whether you're submitting to <em>IEEE, ACM, Springer, Elsevier, or MDPI</em>, this tool streamlines the formatting
    process and helps researchers focus on their content instead of LaTeX syntax.
  </p>
  <p>
   <strong>Notice:</strong> </p>
<p>1. This system incorporates Large Language Models (LLMs) to assist with content generation and user interaction. Outputs may not always be accurate or complete. Please verify any critical information independently.
</p>
<p>2. If you have images in your own file, please put the images and the newly generated zip file into Overleaf for visualization.</p>
  <h3 style="margin-top:20px;">🔧 Key Features</h3>
  <ul>
    <li>📄 Converts plain text or raw LaTeX into formatted documents</li>
    <li>📚 Supports multiple academic publishers and class templates</li>
    <li>🤖 Powered by various models from <strong>OpenRouter.ai</strong> (e.g., Gemini, GPT, Claude)</li>
    <li>🧠 Auto-detects abstract, keywords, sections, equations, and references</li>
    <li>🔁 Converts input into complete, compilable LaTeX with metadata</li>
    <li>📦 One-click download: bundled ZIP with generated <code>.tex</code> + <code>.cls</code></li>
    <li>🌐 Fully client-accessible and easy to use via web browser</li>
  </ul>
    </div>

      <div class="step">
        <h2>Step 1: Choose AI Model</h2>
        <label for="model_choice">Select AI Model:</label>
         <select name="model_choice" id="model_choice" required>
          <option value="">-- Select a Model --</option>
          <?php foreach ($availableModels as $groupLabel => $models): ?>
            <optgroup label="<?= htmlspecialchars($groupLabel) ?>">
              <?php foreach ($models as $modelKey => $modelDetails): ?>
                <option value="<?= htmlspecialchars($modelKey) ?>" <?= ($selectedModel == $modelKey ? 'selected' : '') ?>
                        <?= (isset($modelDetails['recommended']) && $modelDetails['recommended'] ? 'data-recommended="true"' : '') ?>>
                    <?= htmlspecialchars($modelDetails['display']) ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="step">
        <h2>Step 2: Choose Target Format</h2>
        <label for="format_choice">Select target LaTeX format (formats with example templates are preferred):</label>
        <select name="format_choice" id="format_choice" required>
          <option value="">-- Select a Format --</option>
          <?php foreach ($clsMap as $groupLabel => $formats): ?>
            <?php if (!empty($formats)): ?>
            <optgroup label="<?= htmlspecialchars($groupLabel) ?>">
              <?php foreach ($formats as $opt => $description): ?>
                <?php
                  $hasTemplate = false;
                  if (isset($targetFormatToDir[$opt])) {
                      $templateTexPathForCheck = __DIR__ . '/latex_templates/' . $targetFormatToDir[$opt] . '/template.tex';
                      if (file_exists($templateTexPathForCheck)) {
                          $hasTemplate = true;
                      }
                  }
                  $displayText = htmlspecialchars($opt) . ($hasTemplate ? ' (Example Template Available)' : ' (Generic/No Example)');
                ?>
                <option value="<?= htmlspecialchars($opt) ?>" <?= ($targetFormat == $opt ? 'selected' : '') ?>><?= $displayText ?></option>
              <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="step">
        <h2>Step 3: Provide Paper Content</h2>
        <p><strong>Important:</strong> For best results, provide your content clearly structured (e.g., with "Title:", "Abstract:", "Introduction:", "References:" markers if possible), or as plain text sections. The AI will attempt to generate LaTeX in the style of the chosen format, referencing an example template if available.</p>
        <label for="paper_text">Paste your document text here:</label>
        <textarea name="paper_text" id="paper_text" placeholder="Paste title, abstract, introduction, sections, conclusion, references, etc. here. Or upload a file below. Comments will be removed automatically."><?=htmlspecialchars($inputText)?></textarea>
        <label for="paper_file">Or upload a .txt or .tex file:</label>
        <input type="file" name="paper_file" id="paper_file" accept=".tex,.txt">
      </div>
      <button type="submit" id="convertButton">Convert & View LaTeX</button>
    </form>
  </div>

   <script>
    function removeLatexCommentsJS(text) {
        if (!text) return '';
        const lines = text.split('\n');
        const cleanedLines = lines.map(line => {
            let commentIndex = -1;
            for (let i = 0; i < line.length; i++) {
                if (line[i] === '%') {
                    if (i === 0 || line[i - 1] !== '\\') {
                        commentIndex = i;
                        break;
                    }
                }
            }
            return commentIndex !== -1 ? line.substring(0, commentIndex) : line;
        });
        return cleanedLines.join('\n');
    }
    
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('converterForm');
      const overlay = document.getElementById('overlay');
      const paperText = document.getElementById('paper_text');
      const paperFile = document.getElementById('paper_file');
      const modelChoice = document.getElementById('model_choice');
      const formatChoiceSelect = document.getElementById('format_choice');
      let isSubmitting = false;

      const settingsGearButton = document.getElementById('settingsGearButton');
      const apiKeyDropdown = document.getElementById('apiKeyDropdown');
      const saveApiKeyButton = document.getElementById('saveApiKeyButtonDropdown');
      const newApiKeyInput = document.getElementById('new_api_key_input_dropdown');
      const apiKeyMessage = document.getElementById('apiKeyMessageDropdown');
      const apiKeyStatusDiv = document.getElementById('apiKeyStatusDropdown');

      settingsGearButton.addEventListener('click', function(event) {
          event.stopPropagation();
          apiKeyDropdown.style.display = apiKeyDropdown.style.display === 'block' ? 'none' : 'block';
          if (apiKeyDropdown.style.display === 'block') {
            newApiKeyInput.value = ''; 
            apiKeyMessage.textContent = ''; 
          }
      });

      document.addEventListener('click', function(event) {
          if (!settingsGearButton.contains(event.target) && !apiKeyDropdown.contains(event.target)) {
              apiKeyDropdown.style.display = 'none';
          }
      });
      
      saveApiKeyButton.addEventListener('click', function() {
          const newApiKey = newApiKeyInput.value.trim();
          if (!newApiKey) {
              apiKeyMessage.textContent = 'Please enter an API Key.';
              apiKeyMessage.style.color = 'red';
              return;
          }
          const formData = new FormData();
          formData.append('action', 'save_api_key');
          formData.append('new_api_key', newApiKey);
          saveApiKeyButton.textContent = 'Saving...';
          saveApiKeyButton.disabled = true;

          fetch('<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>', {
              method: 'POST',
              body: formData
          })
          .then(response => response.json())
          .then(data => {
              if (data.success) {
                  apiKeyMessage.textContent = data.message;
                  apiKeyMessage.style.color = 'green';
                  let displayKey = "Custom: " + newApiKey.substring(0, 7) + '****' + newApiKey.substring(newApiKey.length - 3);
                  apiKeyStatusDiv.textContent = 'Current: ' + displayKey;
                  setTimeout(() => { 
                     apiKeyDropdown.style.display = 'none';
                  }, 1500);
              } else {
                  apiKeyMessage.textContent = 'Error: ' + (data.message || 'Could not save API Key.');
                  apiKeyMessage.style.color = 'red';
              }
          })
          .catch(error => {
              apiKeyMessage.textContent = 'Request failed: ' + error;
              apiKeyMessage.style.color = 'red';
          })
          .finally(() => {
            saveApiKeyButton.textContent = 'Save Key';
            saveApiKeyButton.disabled = false;
          });
      });

      const recommendedOption = modelChoice.querySelector('option[data-recommended="true"]');
      if (recommendedOption) {
          let currentSelectedIsValid = false;
          if (modelChoice.value) {
              for (let i = 0; i < modelChoice.options.length; i++) {
                  if (modelChoice.options[i].value === modelChoice.value) {
                      currentSelectedIsValid = true;
                      break;
                  }
              }
          }
          if (!modelChoice.value || !currentSelectedIsValid) {
              recommendedOption.selected = true;
          }
      }
      
      paperText.addEventListener('paste', function(event) {
          setTimeout(() => {
              event.target.value = removeLatexCommentsJS(event.target.value);
          }, 10);
      });
      paperText.addEventListener('change', function(event) {
          event.target.value = removeLatexCommentsJS(event.target.value);
      });
      paperFile.addEventListener('change', function(event) {
          const file = event.target.files[0];
          if (file) {
              const reader = new FileReader();
              reader.onload = function(e) {
                  const fileContent = e.target.result;
                  const cleanedContent = removeLatexCommentsJS(fileContent);
                  paperText.value = cleanedContent; 
              };
              reader.onerror = function() {
                  alert('Error reading the selected file.');
              }
              reader.readAsText(file);
          }
      });
      form.addEventListener('submit', function(event) {
        if (isSubmitting) {
            event.preventDefault();
            return;
        }
        <?php if (isset($_SESSION['form_error_message_display'])) unset($_SESSION['form_error_message_display']); ?>
        if (!modelChoice.value) {
            alert("Please select an AI Model.");
            event.preventDefault(); return;
        }
        if (!formatChoiceSelect.value) {
            alert("Please select a target format.");
            event.preventDefault(); return;
        }
        if (!paperText.value.trim() && paperFile.files.length === 0) {
            alert("Please paste text or upload a file.");
            event.preventDefault(); return;
        }
        isSubmitting = true;
        overlay.style.display = 'flex';
      });
      <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($errorMessage) && (!isset($_POST['action']) || $_POST['action'] !== 'save_api_key')): ?>
        if (overlay) overlay.style.display = 'none';
        isSubmitting = false;
        const errorDiv = document.querySelector('.error');
        if (errorDiv) {
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      <?php endif; ?>
    });
  </script>
</body>
</html>