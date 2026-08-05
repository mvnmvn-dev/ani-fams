<?php

namespace App\Http\Controllers;

use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function update(Request $request, UserRepository $users)
    {
        $user = Auth::user();
        $fullName = trim((string) $request->input('full_name'));
        $email = trim((string) $request->input('email'));
        $redirect = $request->input('redirect', route('dashboard'));

        if ($fullName === '' || $email === '') {
            return back()->with('flash_error', 'Name and email are required.');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('flash_error', 'Please enter a valid email address.');
        }

        if ($users->emailExistsForOther($email, (int) $user->id)) {
            return back()->with('flash_error', 'That email is already in use.');
        }

        $current = (string) $request->input('current_password');
        $new = (string) $request->input('new_password');
        $confirm = (string) $request->input('confirm_password');
        $changingPassword = $current !== '' || $new !== '' || $confirm !== '';

        if ($changingPassword) {
            if ($current === '' || $new === '') {
                return back()->with('flash_error', 'Current and new password are required to change password.');
            }
            if (strlen($new) < 8) {
                return back()->with('flash_error', 'New password must be at least 8 characters.');
            }
            if ($new !== $confirm) {
                return back()->with('flash_error', 'New password confirmation does not match.');
            }
            if (! Hash::check($current, str_replace('$2b$', '$2y$', $users->passwordHash((int) $user->id)))) {
                return back()->with('flash_error', 'Current password is incorrect.');
            }
        }

        $photoPath = $user->photo_path ?? null;
        $removePhoto = $request->boolean('remove_photo');

        if ($removePhoto && $photoPath) {
            $this->deletePhotoFile($photoPath);
            $photoPath = null;
        }

        if ($request->hasFile('photo')) {
            $uploaded = $this->storeProfilePhoto($request->file('photo'));
            if ($uploaded['success'] === false) {
                return back()->with('flash_error', $uploaded['error']);
            }
            if ($photoPath) {
                $this->deletePhotoFile($photoPath);
            }
            $photoPath = $uploaded['filePath'];
        }

        $users->updateProfile((int) $user->id, $fullName, $email, $photoPath);
        if ($changingPassword) {
            $users->updatePassword((int) $user->id, $new);
        }

        $user->full_name = $fullName;
        $user->email = $email;
        $user->photo_path = $photoPath;

        $message = 'Profile updated.';
        if ($changingPassword) {
            $message = 'Profile and password updated.';
        }

        return redirect($redirect)->with('flash_success', $message);
    }

    private function storeProfilePhoto($file): array
    {
        if (! $file || ! $file->isValid()) {
            return ['success' => false, 'error' => 'Please choose a valid image file.'];
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, $allowed, true)) {
            return ['success' => false, 'error' => 'Profile photo must be JPG, PNG, WEBP, or GIF.'];
        }

        if ($file->getSize() > 2 * 1024 * 1024) {
            return ['success' => false, 'error' => 'Profile photo must be 2 MB or smaller.'];
        }

        $uploadDir = public_path('uploads') . DIRECTORY_SEPARATOR;
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $uniqueName = uniqid('fams_avatar_', true) . '.' . $extension;
        $destination = $uploadDir . $uniqueName;

        if (! @move_uploaded_file($file->getPathname(), $destination)
            && ! @rename($file->getPathname(), $destination)
            && ! @copy($file->getPathname(), $destination)) {
            return ['success' => false, 'error' => 'Failed to save the profile photo.'];
        }

        return [
            'success' => true,
            'filePath' => '/uploads/' . $uniqueName,
        ];
    }

    private function deletePhotoFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $relative = ltrim(str_replace(['\\', '..'], ['/', ''], $path), '/');
        if (! str_starts_with($relative, 'uploads/')) {
            return;
        }

        $full = public_path($relative);
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
