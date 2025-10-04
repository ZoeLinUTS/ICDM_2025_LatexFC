# ICDM_2025_LatexFC
This is a repository for our project LatexFC that has been accepted by ICDM 2025

## Overview  
ICDM_2025_LatexFC is a Large Language Model (LLM)-powered framework that automates the conversion of LaTeX manuscripts across heterogeneous publisher-specific templates (IEEE, MDPI, Springer, Elsevier, etc.).  

By integrating **prompt engineering, retrieval-augmented generation (RAG), and structured validation**, the system reduces tedious manual formatting while preserving semantic content and ensuring compilable outputs. 
Please click to watch our [Demonstration Video](https://youtu.be/69NrKQXc-Mw)

## Motivation  
Academic publishing often requires authors to strictly follow different LaTeX templates such as IEEE, MDPI, or Springer. Reformatting manuscripts manually across these styles is tedious, error-prone, and demands significant time and expertise. Existing solutions, such as template-specific scripts, are rigid and frequently fail when dealing with complex documents that include citations, figures, or multi-section structures.  

ICDM_2025_LatexFC addresses these challenges by leveraging large language models (LLMs) with prompt engineering and retrieval-augmented generation. The system reduces manual effort, ensures compilable and accurate LaTeX outputs, and allows researchers to focus more on the quality of their scientific work instead of repetitive formatting tasks.  

## Capabilities  
- Provides an automated LaTeX format conversion platform, allowing authors to seamlessly adapt manuscripts to different publisher templates without tedious manual reformatting.  
- Utilizes large language models with prompt engineering and retrieval-augmented generation to preserve document structure and ensure that outputs remain compilable and compliant with target styles.  
- Enhances academic productivity by bridging the gap between complex formatting requirements and practical usability, making cross-template LaTeX conversion efficient, reliable, and accessible for researchers.  

## Key Features  
- **Automated Format Conversion**: Converts LaTeX manuscripts seamlessly across major publisher templates, including IEEE, MDPI, Springer, and Elsevier.  
- **LLM-Powered Transformation**: Employs advanced large language models with prompt engineering and retrieval-augmented generation to ensure accurate and compliant outputs.  
- **Modular Architecture**: Includes configuration management, input pre-processing, RAG core, and post-processing modules for scalability and reliability.  
- **User-Friendly Interface**: Intuitive workflow with step-by-step guidance for model selection, template choice, and manuscript upload, producing ready-to-compile outputs.  
- **Validation & Reliability**: Built-in post-processing ensures compilable LaTeX code, preserving structure and reducing errors in the final manuscript.

## Architecture
<p align="center">
<img src="Architecture_Diagram.png" width="60%" alt="System Architecture Diagram">
</p>
Our system is deployed using php and needs to be configured in the htdocs path of xampp to enable local deployment and interaction.

### Front-end

The front-end provides an intuitive web interface where users can configure their conversion, submit their paper, and retrieve the final formatted document.

*   **Main Conversion Interface:** The primary entry point for the formatting tool.
    *   **AI Model Selection:** Allows users to choose the specific LLM that will perform the conversion.
    *   **Target Format Selection:** A dropdown menu or list where users can select the desired LaTeX template (e.g., IEEE, Springer, MDPI).
    *   **Content Input:** Provides a flexible area for users to either paste their paper content or upload an existing .tex file.

*   **Results & Download Interface:** The page displayed after the conversion is complete.
    *   **Output Preview:** Shows the generated LaTeX code for user review.
    *   **Download Options:** Offers functionality to download the complete project as a .zip archive (including .tex and class files) or open it directly in Overleaf.

### Back-end

The back-end serves as the core engine, managing the entire conversion workflow from input validation to final output generation.

*   **Input Pre-processing Module:** Cleans and validates the user-submitted content, ensuring that structured sections like Title, Abstract, Introduction, and References are correctly identified and handled.
*   **Configuration Management Module:** Manages the specific formatting rules, packages, and structural differences between various target templates (e.g., IEEE, Springer).
*   **Retrieval-Augmented Generation (RAG) Core:** The central processing unit. It enhances the LLM's performance by providing it with relevant examples from the target LaTeX templates, ensuring high accuracy and formatting consistency.
*   **Prompt Engineering Module:** Constructs a detailed, structured prompt for the LLM by combining system instructions, the target format template, and the pre-processed user content.
*   **Output Packaging & Delivery Module:** Handles the final steps of the process. It validates the generated .tex and required .cls files, packages them into a single .zip archive, and delivers the downloadable file or Overleaf link to the front-end.
