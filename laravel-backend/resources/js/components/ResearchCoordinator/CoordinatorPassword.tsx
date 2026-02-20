import { useForm } from "react-hook-form";
import api from "../api/axios";
import axios from "axios";
import { AiOutlineLoading3Quarters } from "react-icons/ai";
import Button from "../shared/components/Button";

type PasswordCredentials = {
  current_password: string;
  password: string;
  password_confirmation: string;
};

const CoordinatorPassword = ({ userId }: { userId: number }) => {
  const {
    register,
    watch,
    handleSubmit,
    setError,
    reset,
    formState: { errors, isSubmitting },
  } = useForm({
    defaultValues: {
      current_password: "",
      password: "",
      password_confirmation: "",
    },
  });
  const newPassword = watch("password");

  const handlePasswordChange = async (password: PasswordCredentials) => {
    try {
      await api.post("/api/password-reset/" + userId, password);
      reset();
    } catch (e) {
      if (axios.isAxiosError(e)) {
        Object.keys(e.response?.data.errors).forEach((field) => {
          setError(field as keyof PasswordCredentials, {
            type: "server",
            message: e.response?.data.errors[field][0],
          });
        });
      }
    }
  };
  return (
    <form
      onSubmit={handleSubmit(handlePasswordChange)}
      className="flex-1 self-start bg-white px-5 py-5"
    >
      <h2 className="text-xl font-semibold">Password</h2>
      <p className="mt-2 text-sm text-gray-500">Change your password</p>
      <label
        htmlFor="password"
        className="mt-16 block font-semibold text-gray-900"
      >
        Current Password
      </label>
      <div>
        <input
          id="password"
          type="password"
          {...register("current_password", {
            required: "This field is required.",
          })}
          placeholder="Your current password"
          className="mt-3 w-full rounded-sm border-b border-gray-400 px-3 py-3 text-sm font-semibold text-gray-800 focus:ring-gray-700"
        />
        {errors.current_password && (
          <span className="text-sm text-red-500">
            {errors.current_password?.message}
          </span>
        )}
      </div>
      <label
        htmlFor="newPassword"
        className="mt-5 block font-semibold text-gray-900"
      >
        New Password
      </label>
      <div>
        <input
          placeholder="Your new password"
          type="password"
          {...register("password", {
            required: "This field is required.",
            minLength: {
              message: "Must be at least 8 characters.",
              value: 8,
            },
          })}
          id="newPassword"
          className="mt-3 w-full rounded-sm border-b border-gray-400 px-3 py-3 text-sm font-semibold text-gray-800 focus:ring-gray-700"
        />
        {errors.password && (
          <span className="block text-sm text-red-500">
            {errors.password?.message}
          </span>
        )}
      </div>
      <label
        htmlFor="confirmPassword"
        className="mt-5 block font-semibold text-gray-900"
      >
        Confirm Password
      </label>
      <div>
        <input
          placeholder="Confirm new password"
          {...register("password_confirmation", {
            required: "This field is required.",
            validate: (val) => val === newPassword || "Password do not match.",
          })}
          type="password"
          id="confirmPassword"
          className="mt-3 w-full rounded-sm border-b border-green-500 px-3 py-3 text-sm font-semibold text-gray-800 focus:ring-gray-700"
        />
        {errors.password_confirmation && (
          <span className="block text-sm text-red-500">
            {errors.password_confirmation?.message}
          </span>
        )}
      </div>
      <Button
        type="submit"
        isDisabled={isSubmitting}
        className="mt-5 w-full rounded-sm bg-blue-500 px-2 py-2 text-sm font-semibold text-white"
      >
        {isSubmitting ? (
          <AiOutlineLoading3Quarters className="animate-spin" />
        ) : (
          "Update Password"
        )}
      </Button>
    </form>
  );
};

export default CoordinatorPassword;
