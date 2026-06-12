<?php

return [
    'speech_pronunciation_assessment_enabled' => env('SPEECH_PRONUNCIATION_ASSESSMENT_ENABLED', false),
    'speech_fluency_assessment_enabled' => env('SPEECH_FLUENCY_ASSESSMENT_ENABLED', false),
    'speech_content_assessment_enabled' => env('SPEECH_CONTENT_ASSESSMENT_ENABLED', false),
    'comment_llm_generation_enabled' => env('COMMENT_LLM_GENERATION_ENABLED', false),

    'show_pronunciation_score' => env('FEATURE_SHOW_PRONUNCIATION_SCORE', false),
    'show_accuracy_score' => env('FEATURE_SHOW_ACCURACY_SCORE', false),
    'show_fluency_score' => env('FEATURE_SHOW_FLUENCY_SCORE', false),
    'show_completeness_score' => env('FEATURE_SHOW_COMPLETENESS_SCORE', false),
    'show_prosody_score' => env('FEATURE_SHOW_PROSODY_SCORE', false),
    'enable_llm_feedback' => env('FEATURE_ENABLE_LLM_FEEDBACK', false),
    'enable_audio_upload' => env('FEATURE_ENABLE_AUDIO_UPLOAD', false),
    'enable_stripe_billing' => env('FEATURE_ENABLE_STRIPE_BILLING', false),
    'enable_admin_console' => env('FEATURE_ENABLE_ADMIN_CONSOLE', false),
];
