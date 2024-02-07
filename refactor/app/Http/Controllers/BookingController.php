<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        // If user_id is provided in the request, get user's jobs
        if ($request->has('user_id')) {
            return $this->getUserJobs($request->get('user_id'));
        }
    
        // If the authenticated user is an admin or superadmin, get all users' jobs
        if ($this->isAdminOrSuperAdmin($request)) {
            return $this->getAllUsersJobs($request);
        }
    
        // Default response if none of the above conditions are met
        return response()->json(['message' => 'Unauthorized'], 401);
    }
    
    private function getUserJobs($userId)
    {
        return $this->repository->getUsersJobs($userId);
    }
    
    private function isAdminOrSuperAdmin($request)
    {
        $authenticatedUserType = $request->__authenticatedUser->user_type;
        return $authenticatedUserType == env('ADMIN_ROLE_ID') || $authenticatedUserType == env('SUPERADMIN_ROLE_ID');
    }
    
    private function getAllUsersJobs($request)
    {
        return $this->repository->getAll($request);
    }
    

    /**
     * @param $id
     * @return mixed
     */
    public function show(int $id)
    {
        $job = $this->jobRepository->with('translatorJobRel.user')->find($id);

        return response()->json($job);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $data = $request->all();
        $authenticatedUser = $request->__authenticatedUser;
    
        $response = $this->repository->store($authenticatedUser, $data);
    
        return response($response);
    }
    

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $data = $request->except(['_token', 'submit']);
        $authenticatedUser = $request->__authenticatedUser;
        $response = $this->repository->updateJob($id, $data, $authenticatedUser);
    
        return response($response);
    }
    

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
{
    try {
        // Retrieve admin sender email from configuration
        $adminSenderEmail = config('app.adminemail');

        // Retrieve request data
        $data = $request->all();

        // Store job email using repository
        $response = $this->repository->storeJobEmail($data);

        // Return success response
        return response()->json($response);
    } catch (\Exception $e) {
        // Return error response in case of any exception
        return response()->json(['error' => $e->getMessage()], 500);
    }
}


    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
{
    // Check if 'user_id' parameter exists in the request
    $userId = $request->input('user_id');

    if ($userId !== null) {
        // Call the repository method to get user's job history
        $response = $this->repository->getUsersJobsHistory($userId, $request);
        
        // Return the response
        return response($response);
    }

    // If 'user_id' parameter is not provided, return null
    return null;
}


    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
{
    $data = $request->all();
    $user = $request->user();

    $response = $this->repository->acceptJob($data, $user);

    return response()->json($response);
}


public function acceptJobWithId(Request $request)
{
    $jobId = $request->input('job_id');
    $user = $request->user();

    $response = $this->repository->acceptJobWithId($jobId, $user);

    return response()->json($response);
}

/**
 * Cancel a job.
 *
 * @param Request $request
 * @return \Illuminate\Http\Response
 */
public function cancelJob(Request $request)
{
    $requestData = $request->all();
    $user = $request->user();

    $response = $this->repository->cancelJobAjax($requestData, $user);

    return response()->json($response);
}


    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->endJob($data);

        return response($response);

    }

    public function customerNotCall(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->customerNotCall($data);

        return response($response);

    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $user = $request->__authenticatedUser;

        $response = $this->repository->getPotentialJobs($user);

        return response($response);
    }

    public function distanceFeed(Request $request)
    {
        $data = $request->all();

        $distance = $data['distance'] ?? "";
        $time = $data['time'] ?? "";
        $jobid = $data['jobid'] ?? "";
        $session = $data['session_time'] ?? "";
        $flagged = $data['flagged'] === 'true' ? 'yes' : 'no';
        $manually_handled = $data['manually_handled'] === 'true' ? 'yes' : 'no';
        $by_admin = $data['by_admin'] === 'true' ? 'yes' : 'no';
        $admincomment = $data['admincomment'] ?? "";
    
        if ($distance || $time) {
            Distance::where('job_id', $jobid)->update(['distance' => $distance, 'time' => $time]);
        }
    
        if ($admincomment || $session || $flagged || $manually_handled || $by_admin) {
            Job::where('id', $jobid)->update([
                'admin_comments' => $admincomment,
                'flagged' => $flagged,
                'session_time' => $session,
                'manually_handled' => $manually_handled,
                'by_admin' => $by_admin
            ]);
        }
    
        return response('Record updated!');
    
    }

    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return response($response);
    }

    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');

        return response(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $job_data = $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }

}
