import React, { useEffect, useState } from "react";
import Button from "../../../shared/components/Button";
import { VscEye, VscEyeClosed } from "react-icons/vsc";
import { UserDataTable, UserType } from "../../types";
import { AiOutlineLoading3Quarters } from "react-icons/ai";

type CoordinatorModalType = {
  isOpen: boolean;
  email: string;
  password: string;
  faculty: UserDataTable | undefined;
  setEmail: React.Dispatch<React.SetStateAction<string>>;
  setPassword: React.Dispatch<React.SetStateAction<string>>;
  loading: boolean;
  errors: { email: string; password: string };
  onConfirm: () => void;
  onCancel: () => void;
};

const AddCoordinatorModal = ({
  isOpen,
  email,
  password,
  setEmail,
  setPassword,
  onCancel,
  onConfirm,
  faculty,
  loading,
  errors,
}: CoordinatorModalType) => {
  const [showPassword, setShowPassword] = useState(false);

  useEffect(() => {
    setEmail("");
    setPassword("");
    setShowPassword(false);
  }, []);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="w-96 rounded bg-white p-6 shadow-lg">
        <h2 className="text-md mb-4 text-center font-semibold">
          Research Coordinator Credentials
        </h2>
        <p className="my-3">
          Create <span className="font-bold">Research Coordinator</span> account
          for <span className="font-bold">{faculty?.name}</span> for the{" "}
          <span className="font-bold">{faculty?.college}</span> department.
        </p>
        <label htmlFor="email">Email</label>
        <input
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          id="email"
          type="email"
          placeholder="johndoe@example.com"
          required
          className={`block w-full rounded-md border ${errors.email !== "" && "border-red-500"} p-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300`}
        />
        {errors.email && <p className="text-sm text-red-500">{errors.email}</p>}
        <div className="relative mt-2">
          <label htmlFor="password">Password</label>
          <input
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            type={showPassword ? "text" : "password"}
            id="password"
            placeholder="******"
            required
            className={`block w-full rounded-md border ${errors.password !== "" && "border-red-500"} p-3 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300`}
          />
          {errors.password && (
            <p className="text-sm text-red-500">{errors.password}</p>
          )}
          <button
            type="button"
            onClick={() => setShowPassword(!showPassword)}
            className="absolute bottom-14 right-3 top-1/2 text-gray-500"
          >
            {showPassword ? (
              <VscEye className="h-5 w-5" />
            ) : (
              <VscEyeClosed className="h-5 w-5" />
            )}
          </button>
        </div>
        <div className="mt-2 flex justify-end gap-4">
          <Button
            className="rounded bg-gray-200 px-3 py-2 text-sm font-semibold hover:bg-gray-300"
            onClick={onCancel}
            type="button"
          >
            Cancel
          </Button>
          <Button
            type="button"
            isDisabled={email === "" || password === "" || loading}
            onClick={onConfirm}
            className="rounded bg-blue-500 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-600 disabled:cursor-pointer"
          >
            {loading ? (
              <AiOutlineLoading3Quarters className="animate-spin transition-all duration-300" />
            ) : (
              "Save"
            )}
          </Button>
        </div>
      </div>
    </div>
  );
};

export default AddCoordinatorModal;
