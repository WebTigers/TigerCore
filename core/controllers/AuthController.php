<?php
/**
 * AuthController — the login gateway (default namespace).
 *
 * Guest-accessible (allowed in core acl.ini), because the authorization plugin
 * redirects unauthenticated callers here. Delegates to the Tiger_Service_
 * Authentication kernel service — which is reserved from /api, so login is a
 * plain controller route (/auth/login), never an /api service.
 */
class AuthController extends Tiger_Controller_Action
{
    /** POST /auth/login {identifier,password} -> JSON identity. GET -> a minimal form. */
    public function loginAction()
    {
        $request = $this->getRequest();

        if ($request->isPost()) {
            $identity = (new Tiger_Service_Authentication())->login(
                (string) $request->getPost('identifier'),
                (string) $request->getPost('password')
            );
            if ($identity) {
                $this->_json(['result' => 1, 'data' => $identity]);
            } else {
                $this->_json(['result' => 0, 'message' => 'api.error.login_failed'], 401);
            }
            return;
        }

        // GET: a bare login form (a themed view replaces this later).
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $this->getResponse()
            ->setHeader('Content-Type', 'text/html; charset=UTF-8')
            ->setBody(
                '<form method="post" action="/auth/login">'
                . '<input name="identifier" placeholder="email"> '
                . '<input name="password" type="password" placeholder="password"> '
                . '<button type="submit">Sign in</button></form>'
            );
    }

    /** Destroy the session and return home. */
    public function logoutAction()
    {
        (new Tiger_Service_Authentication())->logout();
        $this->_helper->redirector->gotoUrl('/');
    }

    /** GET /auth/me -> the current identity as JSON (handy for clients + verification). */
    public function meAction()
    {
        $identity = (new Tiger_Service_Authentication())->getIdentity();
        $this->_json(['result' => $identity ? 1 : 0, 'data' => $identity]);
    }
}
