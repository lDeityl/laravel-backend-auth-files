<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\FileAccess;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FileController extends Controller
{
    //
    public function uploadFiles(Request $request)
    {
        $allowedExtensions = ['doc', 'pdf', 'docx', 'zip', 'jpeg', 'jpg', 'png'];

        $validator = Validator::make($request-> all(), [
             'files' => 'required'
        ]);

        if($validator->fails())
        {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->toArray(),
            ], 422);
        }

        $file = $request->file('files');
        $fileID = uniqid();
        $fileName = $fileID . '.' . $file->getClientOriginalExtension();
        $extensions = $file->getClientOriginalExtension();

        if(!in_array($extensions, $allowedExtensions)){
            return response()->json([
                'success' => false,
                'message' => 'File not loaded',
                'name' => $file->getClientOriginalName()
            ]);
        }

        if($file->getSize() > 2 * 1024 * 1024)
        {
            return response()->json([
                'success' => false,
                'message' => 'File size exceed 2 MB',
                'name' => $file->getClientOriginalName()
            ]);
        }

        Storage::disk('local')->put($fileName, file_get_contents($file));

        File::create([
            'name' => $file->getClientOriginalName(),
            'file_path' => url('/files' . $fileName),
            'file_id' => $fileID,
            'user_id' => auth()->id(),
        ]);

        FileAccess::create([
            'file_id' => $fileID,
            'user_id' => auth()->id(),
            'type' => 'author'
        ]);

        return [
            'success' => true,
            'message' => 'Success',
            'name' => $file->getClientOriginalName(),
            'url' => url('files/' . $fileName),
            'file_id' => $fileID
        ];
    }

    public function rename(Request $request, $file_id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string'
        ]);

        if($validator->fails())
        {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->toArray(),
            ], 422);
        }

        $file = File::where('file_id', $file_id)->first();

        if (!$file) {
            return response()->json([
                'error' => 'Not found'
            ], 404);
        }

        if ($file->user_id !== auth()->id()) {
            return response()->json([
                'error' => 'Forbidden for you'
            ], 403);
        }

        $file->name = $request->input('name');
        $file->save();

        return response()->json([
            'success' => true,
            'message' => 'Renamed',
        ], 200);
    }
    public function delete(Request $request, $file_id)
    {
        $file = File::where('file_id', $file_id)->first();
        $fileAccess = FileAccess::where('file_id', $file_id);

        if (!$file) {
            return response()->json([
                'error' => 'Not found'
            ], 404);
        }

        if ($file->user_id !== auth()->id()) {
            return response()->json([
                'error' => 'Forbidden for you'
            ], 403);
        }

        Storage::disk('local')->delete(
            $this->expolodeURL($file->file_path)
        );

        $fileAccess->delete();
        $file->delete();

        return response()->json([
            'success' => true,
            'message' => 'File already deleted'
        ]);
    }

    public function download(Request $request, $file_id)
    {
        $file = File::where('file_id', $file_id)->first();

        if(!$file){
            return response()->json([
                'error' => 'Not found'
            ], 404);
        }

        $filePath = $this->expolodeURL($file->file_path);
        return response()->download(
            storage_path("app/{$filePath}")
        );
    }

    public function addAccess(Request  $request, $file_id)
    {
        $validator = Validator::make($request->all(),[
            'email' => 'required'
        ]);

        if($validator->fails())
        {
            return response() -> json([
                'success' => false,
                'message' => $validator->errors()->toArray(),
            ], 422);
        }

        $file = File::where('file_id', $file_id)->first();

        if(!$file)
        {
            return response()->json([
                'error' => 'Not found'
            ], 404);
        }

        if($file->user_id !== auth()->id())
        {
            return response()->json([
                'error' => 'Forbidden for you'
            ], 403);
        }

        $user = User::where('email', $request->input('email'))->first();

        if(!$user){
            return response()->json([
                'error' => 'Not found'
            ], 404);
        }

        $access = FileAccess::where('file_id', $file_id)->where('user_id', $user->id)->first();

        if (!$access) {
            $access = new FileAccess;
            $access->file_id = $file->file_id;
            $access->user_id = $user->id;
            $access->save();
        }

        $dataFileAccess = FileAccess::where('file_id', $file->file_id)->get();

        $response = [];
        foreach ($dataFileAccess as $access) {
            $userId = $access->user_id;

            $user = User::find($userId);

            if ($user) {
                $firstName = $user->first_name;
                $lastName = $user->last_name;
                $email = $user->email;

                $response[] = [
                    'fullname' => $firstName . ' ' . $lastName,
                    'email' => $email,
                    'type' => $access->type,
                ];
            }
        }

        return response()->json($response);
    }

    public function deleteAccess(Request $request, $file_id)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->toArray(),
            ], 422);
        }

        $file = File::where('file_id', $file_id)->first();

        if ($file->user_id !== auth()->id()) {
            return response()->json(['error' => 'Forbidden for you'], 403);
        }

        $authUser = User::where('id', auth()->id())->first();

        // Попытка удаления самого себя
        if ($request->input('email') === $authUser->email) {
            return response()->json(['error' => 'Запрещено удаление самого себя'], 403);
        }

        $userAcc = User::where('email', $request->input('email'))->first();


        $access = FileAccess::where('file_id', $file->file_id)->where('user_id', $userAcc->id)->first();

        if (!$access) {
            return response()->json(['error' => 'Пользователь не найден в списке соавторов'], 404);
        }

        $access->delete();

        $dataFileAccess = FileAccess::where('file_id', $file->file_id)->get();

        $response = [];
        foreach ($dataFileAccess as $access) {
            $user = User::find($access->user_id);

            if ($user) {
                $response[] = [
                    'fullname' => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                    'type' => $access->type,
                ];
            }
        };

        return response()->json($response);
    }

    public function getFiles(Request $request)
    {
        $filesByUser = File::where('user_id', auth()->id())->get();
        $response = [];

        foreach ($filesByUser as $file) {
            $accesessByFiles = FileAccess::where('file_id', $file->file_id)->get();

            foreach ($accesessByFiles as $access) {
                $user = User::find($access->user_id);

                if ($user) {

                    $response[] = [
                        'file_id' => $file->file_id,
                        'name' => $file->name,
                        'url' => $file->file_path,
                        'accesses' => [
                            'fullname' => $user->first_name . ' ' . $user->last_name,
                            'email' => $user->email,
                            'type' => $access->type,
                        ]
                    ];
                }
            }
        }

        return response()->json($response);
    }

    public function getSharedFiles()
    {
        return $fileAccessesByUser = FileAccess::where('user_id', auth()->id())->where('author', 'co-author')->get();
    }

    private function expolodeURL($url)
    {
        $parts = explode('/', $url);
        return end($parts);
    }
}
