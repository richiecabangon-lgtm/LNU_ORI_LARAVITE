import { useForm } from "react-hook-form";
import Button from "../../shared/components/Button";
import api from "../../api/axios";
import axios from "axios";
import { useAuthContextProvider } from "../../../hooks/hooks";

type PasswordCredentials = {
  current_password: string;
  password: string;
  password_confirmation: string;
};
const PasswordSettings = () => {
  const id = useAuthContextProvider().user?.id;
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

  const handlePasswordChange = async (data: PasswordCredentials) => {
    try {
      await api.post("/api/password-reset/" + id, data);
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
    <section>
      <form
        onSubmit={handleSubmit(handlePasswordChange)}
        className="space-y-10 rounded-md bg-white p-10 shadow-custom"
      >
        <h1 className="text-2xl font-bold text-gray-700">Change Password</h1>
        <div>
          <label
            htmlFor="currentPassword"
            className="mb-2 block font-bold text-gray-700"
          >
            Current Password
          </label>
          <input
            id="currentPassword"
            type="password"
            {...register("current_password", {
              required: "This field is required.",
            })}
            className="w-1/3 rounded-md border-2 border-gray-400 px-3 py-2 focus:outline-2 focus:outline-gray-500"
          />
        </div>
        {errors.current_password && (
          <span className="text-sm text-red-500">
            {errors.current_password?.message}
          </span>
        )}
        <div>
          <label
            htmlFor="newPassword"
            className="mb-2 block font-bold text-gray-700"
          >
            New Password
          </label>
          <input
            id="newPassword"
            type="password"
            {...register("password", {
              required: "This field is required.",
              minLength: {
                message: "Must be at least 8 characters.",
                value: 8,
              },
            })}
            className="w-1/3 rounded-md border-2 border-gray-400 px-3 py-2 focus:outline-2 focus:outline-gray-500"
          />
          {errors.password && (
            <span className="block text-sm text-red-500">
              {errors.password?.message}
            </span>
          )}
        </div>
        <div>
          <label
            htmlFor="confirmPassword"
            className="mb-2 block font-bold text-gray-700"
          >
            Confirm Password
          </label>
          <input
            id="confirmPassword"
            type="password"
            {...register("password_confirmation", {
              required: "This field is required.",
              validate: (val) =>
                val === newPassword || "Password do not match.",
            })}
            className="w-1/3 rounded-md border-2 border-gray-400 px-3 py-2 focus:outline-2 focus:outline-gray-500"
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
          className="mt-10 w-36 rounded-md bg-blue-500 px-2 py-3 text-sm font-semibold text-white"
        >
          {isSubmitting ? "Loading.." : "Update Password"}
        </Button>
      </form>
    </section>
  );
};

export default PasswordSettings;
