<?php
// server/app/Http/Controllers/SurveyController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Survey; 
use App\Models\SurveyQuestion;
use App\Models\SurveyOption; 
use App\Models\FeedbackResponse; 
use App\Models\QuestionResponse; 
use App\Models\SurveySection;

class SurveyController extends Controller
{
    ///////////////////////////////Creating Surveys////////////////////////////////////
    public function saveSurvey(Request $request)
    {
        try {
            // Validate the request payload
            $validatedData = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'sections' => 'required|array',
                'sections.*.section_title' => 'required|string|max:255',
                'sections.*.section_description' => 'nullable|string',
                'sections.*.questions' => 'required|array',
                'sections.*.questions.*.question_text' => 'required|string|max:255',
                'sections.*.questions.*.question_type' => 'required|string|in:Multiple Choice,Open-ended,Rating',
                'sections.*.questions.*.options' => 'array|required_if:sections.*.questions.*.question_type,Multiple Choice',
                'sections.*.questions.*.options.*.option_text' => 'required_with:sections.*.questions.*.options|string|max:255',
                'sections.*.questions.*.options.*.option_value' => 'nullable|integer',
            ]);
    
            // Create the survey
            $survey = Survey::create([
                'title' => $validatedData['title'],
                'description' => $validatedData['description'],
                'creation_date' => now(),
                'start_date' => $validatedData['start_date'],
                'end_date' => $validatedData['end_date']
            ]);
    
            // Check if the survey was created successfully
            if (!$survey || !$survey->survey_id) {
                return response()->json(['error' => 'Failed to create survey.'], 500);
            }
    
            // Loop through each section and create them with questions and options
            foreach ($validatedData['sections'] as $sectionData) {
                // Create the section and link it to the survey
                $section = SurveySection::create([
                    'survey_id' => $survey->survey_id,
                    'section_title' => $sectionData['section_title'],
                    'section_description' => $sectionData['section_description']
                ]);
    
                // Check if section was created successfully
                if (!$section || !$section->section_id) {
                    \Log::error('Failed to create section for survey ID: ' . $survey->survey_id);
                    continue; // Skip to the next section if section creation failed
                }
    
                // Loop through each question in the section
                foreach ($sectionData['questions'] as $questionData) {
                    // Create the question and link it to the section
                    $question = SurveyQuestion::create([
                        'survey_id' => $survey->survey_id,
                        'section_id' => $section->section_id,
                        'question_text' => $questionData['question_text'],
                        'question_type' => $questionData['question_type']
                    ]);
    
                    // Log error if question creation failed
                    if (!$question || !$question->question_id) {
                        \Log::error('Failed to create question for section ID: ' . $section->section_id);
                        continue; // Skip to the next question if question creation failed
                    }
    
                    // If the question has options, add them
                    if (isset($questionData['options']) && in_array($questionData['question_type'], ['Multiple Choice', 'Rating'])) {
                        foreach ($questionData['options'] as $optionData) {
                            SurveyOption::create([
                                'question_id' => $question->question_id,
                                'option_text' => $optionData['option_text'],
                                'option_value' => $optionData['option_value'],
                            ]);
                        }
                    }
                }
            }
    
            // Return a successful response
            return response()->json(['message' => 'Survey with sections and questions created successfully.', 'survey' => $survey], 201);
    
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error in saveSurvey: ' . $e->getMessage());
    
            // Return a response with error details
            return response()->json([
                'error' => 'An error occurred while creating the survey.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    

    ///////////////////////////////Deleting Surveys////////////////////////////////////
    /**
     * Delete a specific survey along with its questions and options.
     *
     * @param int $surveyId
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteSurvey($surveyId)
    {
        // Find the survey by ID
        $survey = Survey::find($surveyId);

        // Check if the survey exists
        if (!$survey) {
            return response()->json(['error' => 'Survey not found'], 404);
        }

        // Get all questions associated with the survey
        $questions = SurveyQuestion::where('survey_id', $surveyId)->get();

        // Loop through each question and delete associated options
        foreach ($questions as $question) {
            SurveyOption::where('question_id', $question->question_id)->delete();
            $question->delete(); // Delete the question itself
        }

        // Delete the survey itself
        $survey->delete();

        return response()->json(['message' => 'Survey and its associated questions and options deleted successfully'], 200);
    }


    ///////////////////////////////Fetching Surveys////////////////////////////////////

/**
 * Get a survey along with its sections, questions, and options.
 *
 * @param int $surveyId
 * @return \Illuminate\Http\JsonResponse
 */
    public function getSurveyWithQuestions($surveyId)
    {
        try {
            // Fetch the survey along with its sections, questions, and options
            $survey = Survey::with(['sections.questions.options'])->where('survey_id', $surveyId)->first();
        
            // Check if the survey exists
            if (!$survey) {
                return response()->json(['error' => 'Survey not found'], 404);
            }
        
            // Format the response structure with sections, questions, and options
            return response()->json([
                'survey' => $survey->title,
                'description' => $survey->description,
                'start_date' => $survey->start_date,
                'end_date' => $survey->end_date,
                'sections' => $survey->sections->map(function ($section) {
                    return [
                        'section_id' => $section->section_id,
                        'section_title' => $section->section_title,
                        'section_description' => $section->section_description,
                        'questions' => $section->questions->map(function ($question) {
                            return [
                                'question_id' => $question->question_id,
                                'question_text' => $question->question_text,
                                'question_type' => $question->question_type,
                                'options' => $question->options->map(function ($option) {
                                    return [
                                        'option_id' => $option->option_id,
                                        'option_text' => $option->option_text,
                                        'option_value' => $option->option_value,
                                    ];
                                })
                            ];
                        }),
                    ];
                }),
            ], 200);

        } catch (\Exception $e) {
            // Log error and return a JSON response with an error message
            \Log::error('Error in getSurveyWithQuestions: ' . $e->getMessage());

            return response()->json([
                'error' => 'An error occurred while fetching the survey details.',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Get all surveys that the authenticated alumni has not yet answered.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUnansweredSurveys()
    {
        try {
            // Get the authenticated alumni ID
            $alumniId = Auth::id();

            // Fetch all surveys that the alumni has not yet responded to
            $unansweredSurveys = Survey::whereDoesntHave('feedbackResponses', function ($query) use ($alumniId) {
                $query->where('alumni_id', $alumniId);
            })
            ->select('survey_id', 'title', 'description', 'start_date', 'end_date', 'creation_date')
            ->orderBy('creation_date', 'desc')
            ->get();

            // Check if any surveys are available
            if ($unansweredSurveys->isEmpty()) {
                return response()->json(['message' => 'No surveys available for you to answer.'], 404);
            }

            // Return the surveys list
            return response()->json([
                'success' => true,
                'surveys' => $unansweredSurveys
            ], 200);
        } catch (\Exception $e) {
            // Log the error and return a response with error details
            \Log::error('Error fetching unanswered surveys: ' . $e->getMessage());

            return response()->json(['error' => 'Failed to fetch surveys. Please try again later.'], 500);
        }
    }


    public function getAnsweredSurveys()
    {
        try {
            // Get the authenticated alumni ID
            $alumniId = Auth::id();

            // Fetch all surveys that the alumni has already responded to
            $answeredSurveys = Survey::whereHas('feedbackResponses', function ($query) use ($alumniId) {
                $query->where('alumni_id', $alumniId);
            })
            ->select('survey_id', 'title', 'description', 'start_date', 'end_date', 'creation_date')
            ->orderBy('creation_date', 'desc')
            ->get();

            // Check if any surveys are available
            if ($answeredSurveys->isEmpty()) {
                return response()->json(['message' => 'You have not answered any surveys yet.'], 404);
            }

            // Return the list of answered surveys
            return response()->json([
                'success' => true,
                'surveys' => $answeredSurveys
            ], 200);
        } catch (\Exception $e) {
            // Log the error and return a response with error details
            \Log::error('Error fetching answered surveys: ' . $e->getMessage());

            return response()->json(['error' => 'Failed to fetch answered surveys. Please try again later.'], 500);
        }
    }


    /**
     * Get all surveys created by the admin.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllSurveys()
    {
        // Fetch all surveys with basic details
        $surveys = Survey::select('survey_id', 'title', 'description', 'start_date', 'end_date', 'creation_date')
                         ->orderBy('creation_date', 'desc') // Optional: Order by creation date
                         ->get();

        // Check if any surveys are available
        if ($surveys->isEmpty()) {
            return response()->json(['message' => 'No surveys found'], 404);
        }

        // Return the surveys list
        return response()->json([
            'success' => true,
            'surveys' => $surveys
        ], 200);
    }

    ///////////////////////////////Survey Participation////////////////////////////////////

    /**
     * Get questions for a given survey.
     *
     * @param int $surveyId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSurveyQuestions($surveyId)
    {
        $survey = Survey::with('questions.options')->find($surveyId);

        if (!$survey) {
            return response()->json(['error' => 'Survey not found'], 404);
        }

        return response()->json($survey, 200);
    }

    /**
     * Submit survey response by an alumni.
     *
     * @param Request $request
     * @param int $surveyId
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitSurveyResponse(Request $request, $surveyId)
    {
        $alumniId = Auth::id(); // Get the authenticated alumni ID
    
        // Check if the survey exists
        $survey = Survey::find($surveyId);
        if (!$survey) {
            return response()->json(['error' => 'Survey not found'], 404);
        }
    
        // Check if the alumni has already responded to this survey
        $existingResponse = FeedbackResponse::where('survey_id', $surveyId)
                                            ->where('alumni_id', $alumniId)
                                            ->first();
    
        if ($existingResponse) {
            return response()->json(['error' => 'You have already submitted a response for this survey.'], 409);
        }
    
        // Validate the request payload
        $validatedData = $request->validate([
            'responses' => 'required|array',
            'responses.*.question_id' => [
                'required',
                'exists:survey_questions,question_id',
                function ($attribute, $value, $fail) use ($surveyId) {
                    // Ensure question belongs to the survey
                    if (!SurveyQuestion::where('question_id', $value)->where('survey_id', $surveyId)->exists()) {
                        $fail('The question does not belong to the specified survey.');
                    }
                },
            ],
            'responses.*.option_id' => 'nullable|exists:survey_options,option_id', // Nullable for open-ended responses
            'responses.*.response_text' => 'nullable|string' // Text response if option_id is not selected
        ]);
    
        // Create a feedback response record
        $feedbackResponse = FeedbackResponse::create([
            'survey_id' => $surveyId,
            'alumni_id' => $alumniId,
            'response_date' => now()
        ]);
    
        // Save individual question responses
        foreach ($validatedData['responses'] as $response) {
            QuestionResponse::create([
                'response_id' => $feedbackResponse->response_id,
                'question_id' => $response['question_id'],
                'option_id' => $response['option_id'] ?? null,
                'response_text' => $response['response_text'] ?? null,
            ]);
        }
    
        return response()->json(['message' => 'Survey responses submitted successfully.'], 201);
    }

    public function getSurveyResponses($surveyId)
    {
        try {
            // Fetch the survey with sections, questions, and alumni responses
            $survey = Survey::with([
                'sections.questions',  // Fetch sections with questions
                'feedbackResponses.alumni:alumni_id,email,first_name,last_name', // Fetch alumni details
                'feedbackResponses.questionResponses.surveyOption'  // Fetch question responses with options
            ])->where('survey_id', $surveyId)->first();
    
            // Check if survey exists
            if (!$survey) {
                return response()->json(['error' => 'Survey not found'], 404);
            }
    
            // Organize responses by sections and questions
            $responses = [
                'survey_id' => $survey->survey_id,
                'title' => $survey->title,
                'sections' => $survey->sections->map(function ($section) use ($survey) {
                    return [
                        'section_id' => $section->section_id,
                        'section_title' => $section->section_title,
                        'questions' => $section->questions->map(function ($question) use ($survey) {
                            return [
                                'question_id' => $question->question_id,
                                'question_text' => $question->question_text,
                                'responses' => $survey->feedbackResponses->map(function ($feedbackResponse) use ($question) {
                                    // Find the response for this specific question
                                    $questionResponse = $feedbackResponse->questionResponses
                                        ->firstWhere('question_id', $question->question_id);
    
                                    return [
                                        'alumni_id' => $feedbackResponse->alumni_id,
                                        'alumni_email' => $feedbackResponse->alumni->email,
                                        'alumni_first_name' => $feedbackResponse->alumni->first_name,
                                        'alumni_last_name' => $feedbackResponse->alumni->last_name,
                                        'response_text' => $questionResponse ? $questionResponse->response_text : null,
                                        'option_text' => optional($questionResponse->surveyOption)->option_text,
                                        'option_value' => optional($questionResponse->surveyOption)->option_value,
                                    ];
                                })
                            ];
                        })
                    ];
                })
            ];
    
            return response()->json(['success' => true, 'data' => $responses], 200);
    
        } catch (\Exception $e) {
            // Log error and return a JSON response with error message
            \Log::error('Error in getSurveyResponses: ' . $e->getMessage());
    
            return response()->json([
                'error' => 'An error occurred while fetching survey responses.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
