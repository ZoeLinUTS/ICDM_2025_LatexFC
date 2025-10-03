<!-- result_display.php -->
<?php
session_start();

// Check if result data exists in session
if (!isset($_SESSION['converted_latex']) || !isset($_SESSION['target_format_for_download'])) {
    $_SESSION['form_error_message'] = "No conversion result found. Please try converting again.";
    header('Location: converter.php');
    exit;
}

$convertedLatex = $_SESSION['converted_latex'];
$targetFormat = $_SESSION['target_format_for_download'];
unset($_SESSION['converted_latex']);
unset($_SESSION['target_format_for_download']);
unset($_SESSION['original_input_text_for_result']);
unset($_SESSION['selected_model_for_result']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversion Result</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin:0; padding:20px; color: #333; }
        .container { max-width:900px; margin:20px auto; background:#fff; padding:20px 30px; border-radius:8px; box-shadow:0 2px 15px rgba(0,0,0,.1);}
        h1 { text-align:center; color: #4a4a4a; margin-bottom: 20px; }
        h2 { color: #5a5a5a; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-top:20px; font-size: 1.3em;}
        textarea#latex_output_display { 
            width:100%; 
            height: 500px; 
            padding:10px; 
            border:1px solid #ccc; 
            border-radius:4px; 
            box-sizing: border-box; 
            font-family: monospace; 
            font-size: 0.9em; 
            background-color: #e9ecef;
            margin-top:10px;
        }
        button.action-button {
            background:#007bff; color:#fff; border:none; padding:12px 20px;
            font-size: 1.1em; border-radius:4px; cursor:pointer;
            margin-top:15px; margin-right:10px; transition: background-color 0.2s ease-in-out;
        }
        button.action-button:hover { background: #0056b3; }
        button.download-button { background: #28a745; }
        button.download-button:hover { background: #218838; }
        .button-container { margin-top: 20px; text-align: right; }
        #overlay-result {
          display: none;
          position: fixed; top: 0; left: 0;
          width: 100%; height: 100%;
          background: rgba(255,255,255,0.8);
          z-index: 9999;
          justify-content: center;
          align-items: center;
          flex-direction: column;
          color: #333;
          font-size: 1.2em;
          text-align: center;
        }
        .spinner-result {
          border: 8px solid #e0e0e0;
          border-top: 8px solid #007bff;
          border-radius: 50%; width: 60px; height: 60px;
          animation: spin-result 1s linear infinite;
          margin-bottom: 20px;
        }
        @keyframes spin-result { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div id="overlay-result">
        <div class="spinner-result"></div>
        <p>Preparing ZIP package...<br>Please wait.</p>
    </div>

    <div class="container">
        <h1>Conversion Successful!</h1>
        
        <h2>Generated LaTeX Output:</h2>
        <p>Target Format: <strong><?= htmlspecialchars($targetFormat) ?></strong></p>
        <textarea id="latex_output_display" readonly><?= htmlspecialchars($convertedLatex) ?></textarea>

        <div class="button-container">
            <button type="button" id="downloadLatexButtonResult" class="action-button download-button">Download ZIP (with .cls)</button>
            <button type="button" onclick="window.location.href='converter.php';" class="action-button">Convert Another</button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var downloadButton = document.getElementById('downloadLatexButtonResult');
        var overlay = document.getElementById('overlay-result');
        var defaultOverlayHTML = overlay.innerHTML; // Store default overlay content

        if (downloadButton) {
            downloadButton.addEventListener('click', function() {
                var latexContent = document.getElementById('latex_output_display').value;
                var selectedFormatValue = "<?= htmlspecialchars($targetFormat, ENT_QUOTES, 'UTF-8') ?>"; // Get from PHP

                if (latexContent.trim() === "") {
                    alert("No content to download.");
                    return;
                }
                if (selectedFormatValue === "") {
                    alert("Target format not identified. Cannot proceed with download.");
                    return;
                }

                overlay.innerHTML = defaultOverlayHTML; // Reset to default spinner
                overlay.style.display = 'flex';

                const formData = new FormData();
                formData.append('latex_content', latexContent);
                formData.append('format_choice', selectedFormatValue);

                fetch('download_zip.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error('Network response was not ok. Status: ' + response.status + '. Server Message: ' + (text || 'Unknown server error'));
                        });
                    }
                    const disposition = response.headers.get('Content-Disposition');
                    let filename = "latex_package.zip"; 
                    if (disposition && disposition.indexOf('attachment') !== -1) {
                        const filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
                        const matches = filenameRegex.exec(disposition);
                        if (matches != null && matches[1]) {
                            filename = matches[1].replace(/['"]/g, '');
                        }
                    }
                    return response.blob().then(blob => ({ blob, filename }));
                })
                .then(({ blob, filename }) => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    overlay.style.display = 'none'; 
                })
                .catch(error => {
                    console.error('Error downloading ZIP:', error);
                    alert('Error creating or downloading ZIP package: ' + error.message);
                    overlay.style.display = 'none'; 
                });
            });
        }
    });
    </script>
        <div style="text-align: center; margin-top: 20px;">
        <a href="https://www.overleaf.com/project" target="_blank" style="background:#00a362; color:#fff; border:none; padding:12px 20px; font-size: 1.1em; border-radius:4px; cursor:pointer; text-decoration:none; transition: background-color 0.2s ease-in-out; display: inline-block;">Open in Overleaf</a>
    </div>
</body>
</html>
