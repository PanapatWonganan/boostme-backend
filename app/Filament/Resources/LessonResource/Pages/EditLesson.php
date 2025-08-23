<?php

namespace App\Filament\Resources\LessonResource\Pages;

use App\Filament\Resources\LessonResource;
use App\Jobs\ProcessVideoJob;
use App\Models\Video;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;

class EditLesson extends EditRecord
{
    protected static string $resource = LessonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function afterSave(): void
    {
        \Log::info('EditLesson afterSave() called for lesson: ' . $this->record->id);
        
        // Try to get video upload from form state
        $formState = $this->form->getState();
        $videoUpload = $formState['video_upload'] ?? null;
        
        \Log::info('Video upload data in afterSave:', ['video_upload' => $videoUpload]);
        
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
    
    // Also try using the newer hook
    protected function handleRecordUpdate($record, array $data): Model
    {
        \Log::info('handleRecordUpdate called', ['data_keys' => array_keys($data)]);
        
        $record = parent::handleRecordUpdate($record, $data);
        
        // Check for video upload in the data array
        if (isset($data['video_upload']) && !empty($data['video_upload'])) {
            \Log::info('Found video_upload in handleRecordUpdate:', ['video_upload' => $data['video_upload']]);
            
            try {
                if (is_array($data['video_upload'])) {
                    foreach ($data['video_upload'] as $file) {
                        if (!empty($file)) {
                            $this->processVideoUpload($file);
                            break;
                        }
                    }
                } elseif (is_string($data['video_upload'])) {
                    $this->processVideoUpload($data['video_upload']);
                }
            } catch (\Exception $e) {
                \Log::error('Error in handleRecordUpdate: ' . $e->getMessage());
            }
        }
        
        return $record;
    }
    
    protected function processVideoUpload(string $tempPath): void
    {
        // Get the uploaded file info - use storage path for checking but store relative path
        $filePath = storage_path('app/' . $tempPath);
        
        if (file_exists($filePath)) {
            // Check if there's an existing video and mark it as replaced
            $existingVideo = $this->record->primaryVideo;
            if ($existingVideo) {
                $existingVideo->update(['status' => 'replaced']);
            }
            
            // Create new video record - IMPORTANT: Store relative path, not absolute
            $video = Video::create([
                'title' => $this->record->title . ' - Video',
                'lesson_id' => $this->record->id,
                'original_filename' => basename($tempPath),
                'original_path' => $tempPath,  // This is already relative (e.g., 'temp-videos/file.mp4')
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
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove video_upload from data as it's not a database field
        unset($data['video_upload']);
        
        return $data;
    }
}
