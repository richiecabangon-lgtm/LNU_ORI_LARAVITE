import React from "react";
import { Navigate } from "react-router-dom";
import { useAuthContextProvider } from "../../hooks/hooks";
import { AiOutlineLoading3Quarters } from "react-icons/ai";

interface ProtectedRouteProps {
  children: React.ReactNode;
  allowedRole?: string;
  redirectPath?: string;
}

const ProtectedRoute = ({
  children,
  allowedRole,
  redirectPath = "/404",
}: ProtectedRouteProps) => {
  // 1. Get the auth loading state and user from context
  const { user, authLoading } = useAuthContextProvider();

  // 2. THE FIX: If the app is still loading (checking if you are logged in),
  // show a spinner. Do NOT redirect yet.
  if (authLoading) {
    return (
      <div className="flex h-screen w-full items-center justify-center">
        <AiOutlineLoading3Quarters className="animate-spin text-4xl text-blue-600" />
      </div>
    );
  }

  // 3. Only redirect to Login if loading is finished and no user was found.
  if (!user) {
    return <Navigate to="/" replace />;
  }

  // 4. Role Check: If user exists but has the wrong role, go to 404/redirect path.
  if (allowedRole && user.roles?.[0]?.name !== allowedRole) {
    return <Navigate to={redirectPath} replace />;
  }

  // 5. If all checks pass, show the page.
  return children;
};

export default ProtectedRoute;