<?php

namespace App\Filament\Resources\LessonResource\Pages;

use App\Filament\Resources\LessonResource;
use App\Jobs\ProcessVideoJob;
use App\Models\Video;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateLesson extends CreateRecord
{
    protected static string $resource = LessonResource::class;
    
    protected function afterCreate(): void
    {
        \Log::info('CreateLesson afterCreate() called for lesson: ' . $this->record->id);
        
        // Try to get video upload from form state
        $formState = $this->form->getState();
        $videoUpload = $formState['video_upload'] ?? null;
        
        \Log::info('Video upload data in afterCreate:', ['video_upload' => $videoUpload]);
        
        if ($videoUpload && !empty($videoUpload)) {
            try {
                // Handle different formats of video upload data
                if (is_array($videoUpload)) {
                    // If it's an array, take the first non-empty element
                    $filePath = null;
                    foreach ($videoUpload as $file) {
                        if (!empty($file)) {
                            $filePath = $file;
                            break;
                        }
                    }
                    if ($filePath) {
                        \Log::info('Processing video upload from array: ' . $filePath);
                        $this->processVideoUpload($filePath);
                    }
                } elseif (is_string($videoUpload)) {
                    \Log::info('Processing video upload from string: ' . $videoUpload);
                    $this->processVideoUpload($videoUpload);
                }
            } catch (\Exception $e) {
                \Log::error('Error processing video upload: ' . $e->getMessage());
                $this->notify('danger', 'Error processing video upload: ' . $e->getMessage());
            }
        } else {
            \Log::info('No video upload found in form state');
        }
    }
    
    protected function processVideoUpload(string $tempPath): void
    {
        // Get the uploaded file info
        $filePath = storage_path('app/' . $tempPath);
        
        if (file_exists($filePath)) {
            // Create video record
            $video = Video::create([
                'title' => $this->record->title . ' - Video',
                'lesson_id' => $this->record->id,
                'original_filename' => basename($tempPath),
                'original_path' => $tempPath,
                'mime_type' => mime_content_type($filePath),
                'file_size' => filesize($filePath),
                'status' => 'pending',
                'metadata' => [
                    'uploaded_by' => auth()->id(),
                    'uploaded_at' => now()->toISOString(),
                ]
            ]);
            
            // Queue video processing job
            ProcessVideoJob::dispatch($video);
            
            // Show notification
            $this->notify('success', 'Video uploaded and queued for processing');
        } else {
            // Show error notification
            $this->notify('danger', 'Video file not found: ' . $tempPath);
        }
    }
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove video_upload from data as it's not a database field
        unset($data['video_upload']);
        
        return $data;
    }
}
