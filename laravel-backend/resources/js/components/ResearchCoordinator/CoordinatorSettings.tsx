import { useRef, useState } from "react";
import { useAuthContextProvider } from "../../hooks/hooks";
import Button from "../shared/components/Button";
import { FaCamera } from "react-icons/fa";
import { User } from "../shared/types/types";
import api from "../api/axios";
import axios from "axios";
import { AiOutlineLoading3Quarters } from "react-icons/ai";
import { useForm } from "react-hook-form";
import CoordinatorPassword from "./CoordinatorPassword";

const getImageUrl = (path: string | null | undefined) => {
  if (!path) return "https://via.placeholder.com/150";
  if (path.startsWith("http://localhost/storage")) {
      return path.replace("http://localhost/", "http://localhost:8000/");
  }
  if (path.startsWith("http")) return path;
  return `http://localhost:8000/storage/${path}`;
};

const CoordinatorSettings = () => {
  const inputFile = useRef<HTMLInputElement | null>(null);
  const [imagePath, setImagePath] = useState(null);
  const { user, setUser } = useAuthContextProvider();

  const {
    register,
    handleSubmit,
    setError,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<User>({
    defaultValues: {
      ...user,
    },
  });

  const userId = watch("id");

  const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const allowedTypes = ["image/png", "image/jpeg"];

    if (e.target.files && !allowedTypes.includes(e.target.files[0].type)) {
      e.target.value = "";
      return alert("Invalid file type.");
    }
    const formImage = new FormData();

    if (e.target.files && e.target.files[0]) {
      formImage.append("image_path", e.target.files[0]);
    }
    try {
      const res = await api.post("/api/file-upload-public", formImage, {
        headers: {
          "Content-Type": "multipart/form-data",
        },
      });
      setImagePath(res.data.data.image_path);
    } catch (e) {
      if (axios.isAxiosError(e)) {
        Object.keys(e.response?.data.errors).forEach((field) => {
          setError(field as keyof User, {
            type: "server",
            message: e.response?.data.errors[field][0],
          });
        });
      }
    }
  };
  const handleUpdate = async (val: User) => {
    try {
      const response = await api.put(`/api/coordinators/${userId}`, {
        ...val,
        image_path: imagePath,
      });
      setUser(response.data.data);
      sessionStorage.setItem("User", JSON.stringify(response.data.data));
    } catch (e) {
      if (axios.isAxiosError(e)) {
        Object.keys(e.response?.data.errors).forEach((field) => {
          setError(field as keyof User, {
            type: "server",
            message: e.response?.data.errors[field][0],
          });
        });
      }
    }
  };
  return (
    <section>
      <h1 className="text-2xl font-semibold">Profile Settings</h1>
      <div className="mt-10 h-screen max-w-full">
        <div className="flex items-center justify-between space-x-10">
          <div className="flex-1 bg-white shadow-sm">
            <div className="relative flex-col items-start justify-center px-9 py-5">
              <h2 className="text-xl font-semibold">Account Information</h2>
              <p className="mt-2 text-sm text-gray-500">Edit your profile</p>
              <div className="relative mt-10 h-20 w-20 overflow-hidden rounded-full border-2 border-blue-500">
                <img
                  src={getImageUrl(imagePath || user?.image_path)}
                  alt="Profile"
                  loading="lazy"
                  className="absolute cursor-pointer object-contain"
                  onClick={() => inputFile.current?.click()}
                />
                <input
                  onChange={handleUpload}
                  ref={inputFile}
                  type="file"
                  className="hidden"
                  accept="image/jpeg, image/png"
                />
              </div>
              <FaCamera
                onClick={() => inputFile.current?.click()}
                className="absolute left-28 top-44 cursor-pointer"
              />

              <form
                onSubmit={handleSubmit(handleUpdate)}
                className="mt-10 w-auto"
              >
                <label
                  htmlFor="fname"
                  className="block font-semibold text-gray-900"
                >
                  First Name
                </label>
                <div>
                  <input
                    id="fname"
                    {...register("fname", {
                      minLength: {
                        message: "Minimum of 2 characters.",
                        value: 2,
                      },
                      required: "First name is required.",
                    })}
                    className="mt-3 w-full rounded-sm border-b border-gray-400 px-3 py-3 text-sm font-semibold text-gray-800 focus:ring-gray-700"
                  />
                  {errors.fname && (
                    <span className="block text-sm text-red-500">
                      {errors.fname?.message}
                    </span>
                  )}
                </div>
                <label
                  htmlFor="mi"
                  className="mt-5 block font-semibold text-gray-900"

                >
                  Middle Initial
                </label>
                <div>
                  <input
                    maxLength={1}
                    id="mi"
                    {...register("mi", {
                      maxLength: {
                        message: "Enter your middle initial only.",
                        value: 1,
                      },
                    })}
                    className="mt-3 w-full rounded-sm border-b border-gray-400 px-3 py-3 text-sm font-semibold text-gray-800 focus:ring-gray-700"
                  />
                  {errors.mi && (
                    <span className="block text-sm text-red-500">
                      {errors.mi?.message}
                    </span>
                  )}
                </div>
                <label
                  htmlFor="lname"
                  className="mt-5 block font-semibold text-gray-900"
                >
                  Last Name
                </label>
                <div>
                  <input
                    id="lname"
                    {...register("lname", {
                      minLength: {
                        message: "Minimum of 2 characters.",
                        value: 2,
                      },
                      required: "Last name is required.",
                    })}
                    className="mt-3 w-full rounded-sm border-b border-gray-400 px-3 py-3 text-sm font-semibold text-gray-800 focus:ring-gray-700"
                  />
                  {errors.lname && (
                    <span className="block text-sm text-red-500">
                      {errors.lname?.message}
                    </span>
                  )}
                </div>
                <label
                  htmlFor="suffix"
                  className="mt-5 block font-semibold text-gray-900"
                >
                  Suffix
                </label>
                <div>
                  <input
                    id="suffix"
                    {...register("suffix", {
                      minLength: {
                        message: "Minimum of 2 characters.",
                        value: 2,
                      },
                    })}
                    className="mt-3 w-full rounded-sm border-b border-gray-400 px-3 py-3 text-sm font-semibold text-gray-800 focus:ring-gray-700"
                  />
                  {errors.suffix && (
                    <span className="block text-sm text-red-500">
                      {errors.suffix?.message}
                    </span>
                  )}
                </div>

                <label
                  htmlFor="email"
                  className="mt-5 block font-semibold text-gray-900"
                >
                  Email
                </label>
                <div>
                  <input
                    id="email"
                    type="email"
                    {...register("email", {
                      pattern: /^\S+@\S+$/i,
                      required: "Email is required.",
                    })}
                    className="mt-3 w-full rounded-sm border-b border-gray-400 px-3 py-3 text-sm font-semibold text-gray-800 focus:ring-gray-700"
                  />
                  {errors.email && (
                    <span className="block text-sm text-red-500">
                      {errors.email?.message}
                    </span>
                  )}
                </div>
                <Button
                  type="submit"
                  isDisabled={isSubmitting}
                  className="mt-5 w-full rounded-sm bg-blue-500 px-2 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed"
                >
                  {isSubmitting ? (
                    <AiOutlineLoading3Quarters className="animate-spin" />
                  ) : (
                    "Update"
                  )}
                </Button>
              </form>
            </div>
          </div>
          <CoordinatorPassword userId={userId} />
        </div>
      </div>
    </section>
  );
};

export default CoordinatorSettings;
