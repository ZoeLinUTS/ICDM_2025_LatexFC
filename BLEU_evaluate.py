import os
import pandas as pd
from nltk.translate.bleu_score import sentence_bleu, SmoothingFunction
import re

def read_tex_file(path):
    try:
        with open(path, 'r', encoding='utf-8') as f:
            return f.read()
    except FileNotFoundError:
        print(f"Error: File not found at {path}")
        return None
    except Exception as e:
        print(f"Error reading file {path}: {e}")
        return None

def preprocess_latex_for_bleu(latex_string):
    if not latex_string:
        return []
    # Remove comments
    text = re.sub(r'%.*?\n', '\n', latex_string)
    
    # Extract content from major metadata and sectioning commands
    text = re.sub(r'\\documentclass\[.*?\]\{.*?\}', ' ', text)
    text = re.sub(r'\\usepackage(\[.*?\])?\{.*?\}', ' ', text)
    
    metadata_tags = r'title|author|date|thanks|keywords'
    section_tags = r'section|subsection|subsubsection|caption|label|cite|ref'
    style_tags = r'textit|textbf|texttt|emph'
    
    text = re.sub(fr'\\({metadata_tags})\{{(.*?)\}}', r'\2', text, flags=re.DOTALL)
    text = re.sub(fr'\\({section_tags})\*?\{{(.*?)\}}', r'\2', text, flags=re.DOTALL)
    text = re.sub(fr'\\({style_tags})\{{(.*?)\}}', r'\2', text, flags=re.DOTALL)

    # Replace environments with placeholders
    env_patterns = {
        'figure': ' FIGURE_PLACEHOLDER ',
        'table': ' TABLE_PLACEHOLDER ',
        'equation': ' EQUATION_PLACEHOLDER ',
    }
    for env, placeholder in env_patterns.items():
        text = re.sub(fr'\\begin\{{{env}}.*?\}.*?\\end\{{{env}.*?\}}', placeholder, text, flags=re.DOTALL | re.IGNORECASE)

    # Extract abstract content
    text = re.sub(r'\\begin\{abstract.*?\}(.*?)\\end\{abstract.*?\}', r'\1', text, flags=re.DOTALL | re.IGNORECASE)

    # Remove remaining generic LaTeX commands
    text = re.sub(r'\\[a-zA-Z]+(\*|\[.*?\]|\{.*?\})*', ' ', text)

    # Replace math environments with placeholders (basic)
    text = re.sub(r'\$.*?\$', ' MATH_INLINE ', text)
    text = re.sub(r'\\\[.*?\\\]', ' MATH_DISPLAY ', text)

    # Clean up brackets and extra whitespace
    text = re.sub(r'[\{\}\[\]]', ' ', text)
    text = re.sub(r'\s+', ' ', text).strip()

    # Tokenize by splitting on spaces
    return text.split()

# Paths
INPUT_PATH = 'input/input.tex'    
OUTPUT_DIR = 'output'             

# Validate input file path
if not os.path.exists(INPUT_PATH):
    print(f"Error: Reference file not found: {INPUT_PATH}. Please check the path.")
    exit()

# Read and preprocess the reference file
original_content = read_tex_file(INPUT_PATH)
if original_content is None:
    exit()

# Preprocess reference into a list of tokens for BLEU score
# BLEU expects a list of reference lists
reference_tokens = [preprocess_latex_for_bleu(original_content)]

if not reference_tokens[0]:
    print(f"Error: Preprocessing the reference file {INPUT_PATH} resulted in empty content. Cannot compute BLEU score.")
    exit()

# Initialize results list and BLEU smoothing function
results = []
chencherry = SmoothingFunction()

# Iterate through all .tex files in the output directory
output_files = [f for f in os.listdir(OUTPUT_DIR) if f.endswith('.tex')]

if not output_files:
    print(f"No .tex files found in the '{OUTPUT_DIR}' directory to evaluate.")
    exit()

for file_name in output_files:
    model_name = file_name.replace('.tex', '')
    generated_path = os.path.join(OUTPUT_DIR, file_name)
    generated_content = read_tex_file(generated_path)

    if generated_content is None:
        results.append((model_name, 0.0, "Failed to read generated file"))
        continue

    # Preprocess the generated file into a list of tokens 
    candidate_tokens = preprocess_latex_for_bleu(generated_content)
    
    bleu_score = 0.0
    status = "OK"

    if not candidate_tokens:
        print(f"Warning: Preprocessed content for {file_name} is empty. BLEU score will be 0.")
        status = "Preprocessed content is empty"
    else:
        try:
            # Calculate BLEU score
            bleu_score = sentence_bleu(reference_tokens,
                                       candidate_tokens,
                                       smoothing_function=chencherry.method1) 
        except ZeroDivisionError:
            status = "BLEU ZeroDivisionError (candidate too short or no overlap)"
        except Exception as e:
            status = f"BLEU calculation error: {e}"

    results.append((model_name, bleu_score, status))
    print(f"Model: {model_name}, BLEU Score: {bleu_score:.4f}, Status: {status}")

# Display results in a formatted table
if results:
    df = pd.DataFrame(results, columns=['Model (Filename)', 'BLEU Score', 'Status'])
    df = df.sort_values(by='BLEU Score', ascending=False)
    
    print("\n--- BLEU Score Evaluation Results ---")
    print(df.to_markdown(index=False))
