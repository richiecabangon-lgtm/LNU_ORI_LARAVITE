import { Outlet, useNavigate } from "react-router-dom";
import { useEffect } from "react";
import { redirectLink } from "../../constant/redirectLinks";
import { useAuthContextProvider } from "../../hooks/hooks";

export default function GuestLayout() {
  const navigate = useNavigate();
  const { user } = useAuthContextProvider();

  useEffect(() => {
    const redirectIfAuthenticated = () => {
      if (!user) return;

      const { roles } = user;
      navigate(redirectLink[roles[0].name]);
    };

    redirectIfAuthenticated();
  }, [user, navigate]);

  return <Outlet />;
}
