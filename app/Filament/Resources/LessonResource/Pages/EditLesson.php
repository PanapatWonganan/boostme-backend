<?php

namespace App\Filament\Resources\LessonResource\Pages;

use App\Filament\Resources\LessonResource;
use App\Jobs\ProcessVideoJob;
use App\Models\Video;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

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
        // Try to get video upload from form state
        $formState = $this->form->getState();
        $videoUpload = $formState['video_upload'] ?? null;
        
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
                        $this->processVideoUpload($filePath);
                    }
                } elseif (is_string($videoUpload)) {
                    $this->processVideoUpload($videoUpload);
                }
            } catch (\Exception $e) {
                \Log::error('Error processing video upload: ' . $e->getMessage());
                $this->notify('danger', 'Error processing video upload: ' . $e->getMessage());
            }
        }
    }
    
    protected function processVideoUpload(string $tempPath): void
    {
        // Get the uploaded file info
        $filePath = storage_path('app/' . $tempPath);
        
        if (file_exists($filePath)) {
            // Check if there's an existing video and mark it as replaced
            $existingVideo = $this->record->primaryVideo;
            if ($existingVideo) {
                $existingVideo->update(['status' => 'replaced']);
            }
            
            // Create new video record
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
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove video_upload from data as it's not a database field
        unset($data['video_upload']);
        
        return $data;
    }
}
