import React, { createContext, useEffect, useState } from "react";
import { User } from "../components/shared/types/types";
import { NavigateFunction } from "react-router-dom";
import api, { setInitialLoadCompleted } from "../components/api/axios";
import axios from "axios";
import { redirectLink } from "../constant/redirectLinks";
import { useUser } from "../hooks/hooks";

type AuthContextType = {
  user: User | null;
  setUser: React.Dispatch<React.SetStateAction<User | null>>;
  handleLogin: (navigate: NavigateFunction) => Promise<void>;
  email: string;
  setEmail: React.Dispatch<React.SetStateAction<string>>;
  password: string;
  setPassword: React.Dispatch<React.SetStateAction<string>>;
  loading: boolean;
  setLoading: React.Dispatch<React.SetStateAction<boolean>>;
  error: string | null;
  setError: React.Dispatch<React.SetStateAction<string | null>>;
  authLoading: boolean;
};

export const AuthContext = createContext<AuthContextType | null>(null);

const storageKey = "auth-user";

const getStoredUser = () => {
  try {
    const stored = sessionStorage.getItem(storageKey);
    return stored ? (JSON.parse(stored) as User) : null;
  } catch (error) {
    console.error(error);
    return null;
  }
};

const setStoredUser = (nextUser: User | null) => {
  try {
    if (!nextUser) {
      sessionStorage.removeItem(storageKey);
      return;
    }
    sessionStorage.setItem(storageKey, JSON.stringify(nextUser));
  } catch (error) {
    console.error(error);
  }
};

export default function AuthContextProvider({
  children,
}: {
  children: React.ReactNode;
}) {
  const [user, setUser] = useState<User | null>(() => getStoredUser());
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const {
    data: userData,
    isLoading: isUserLoading,
    isError: isUserError,
    error: userError,
  } = useUser();

  useEffect(() => {
    if (!isUserLoading) {
      if (userData) {
        setUser(userData);
      } else if (
        isUserError &&
        axios.isAxiosError(userError) &&
        (userError.response?.status === 401 ||
          userError.response?.status === 419)
      ) {
        setUser(null);
      }
      setInitialLoadCompleted(true);
    }
  }, [userData, isUserLoading, isUserError, userError]);

  useEffect(() => {
    setStoredUser(user);
  }, [user]);

  const handleLogin = async (navigate: NavigateFunction) => {
    setLoading(true);
    setError(null);
    try {
        await api.get("/sanctum/csrf-cookie");
      const { data, status } = await api.post("/api/login", {
        email,
        password,
      });

      setUser(data.data);
      const role = data.data.roles[0].name;

      if (status === 200) {
        setEmail("");
        setPassword("");
        navigate(redirectLink[role]);
      }
    } catch (error) {
      if (axios.isAxiosError(error)) {
        setError(error.response?.data.message || "Login failed.");
      } else {
        setError("An unexpected error occurred.");
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        setUser,
        handleLogin,
        email,
        setEmail,
        password,
        setPassword,
        loading: loading,
        setLoading,
        error,
        setError,
        authLoading: isUserLoading && !user,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}
