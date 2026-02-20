import { useEffect, useRef, useState } from "react";
import { useAuthContextProvider } from "../../hooks/hooks";
import Button from "../shared/components/Button";
import { FaCamera } from "react-icons/fa";
import { User } from "../shared/types/types";
import api from "../api/axios";
import axios from "axios";
import { AiOutlineLoading3Quarters } from "react-icons/ai";

const initialData = {
  id: 0,
  fname: "",
  lname: "",
  mi: "",
  suffix: "",
  email: "",
  image_path: "",
  password: "",
  academic_rank: "",
  unit: "",
  college: "",
  created_at: null,
  deleted_at: null,
  updated_at: null,
  roles: [{ id: 1, name: "" }],
};
const getImageUrl = (path: string | null | undefined) => {
  if (!path) return "https://via.placeholder.com/150"; 

  if (path.startsWith("http://localhost/storage")) {
      return path.replace("http://localhost/", "http://localhost:8000/");
  }

  if (path.startsWith("http")) return path; 

  return `http://localhost:8000/storage/${path}`; 
};
const FacultySettings = () => {
  const [data, setData] = useState<User>(initialData);
  const inputFile = useRef<HTMLInputElement | null>(null);
  const [updateAccountLoading, setUpdateAccountLoading] = useState(false);
  const [updatePasswordLoading, setUpdatePasswordLoading] = useState(false);
  const [uploadLoading, setUploadLoading] = useState(false);
  const [password, setPassword] = useState({
    currentPassword: "",
    newPassword: "",
    confirmPassword: "",
  });
  const { user, setError, setUser } = useAuthContextProvider();

  useEffect(() => {
    if (user) {
      setData({
        fname: user.fname,
        lname: user.lname,
        mi: user.mi,
        email: user.email,
        image_path: user.image_path,
        academic_rank: user.academic_rank,
        unit: user.unit,
        suffix: user.suffix,
        id: user.id,
        college: user.college,
        created_at: user.created_at,
        deleted_at: user.deleted_at,
        updated_at: user.updated_at,
        
        // --- ADD THESE TWO LINES ---
        roles: user.roles || [], // Pass the roles (or empty array if null)
      });
    }
  }, [user]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setData((data) => ({
      ...data,
      [e.target.name]: e.target.value,
    }));
  };

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
      setUploadLoading(true);
      const res = await api.post("/api/file-upload-public", formImage, {
        headers: {
          "Content-Type": "multipart/form-data",
        },
      });
      setData({ ...data, image_path: res.data.data.image_path });
    } catch (err) {
      if (axios.isAxiosError(err)) {
        setError(err.message);
      }
    } finally {
      setUploadLoading(false);
    }
  };
  const handleUpdate = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (updateAccountLoading) return;
    try {
      setUpdateAccountLoading(true);
      const response = await api.put(`/api/coordinators/${data.id}`, data);
      setUser(response.data.data);
      sessionStorage.setItem("User", JSON.stringify(response.data.data));
    } catch (error) {
      if (axios.isAxiosError(error)) {
        setError(error.response?.data.message);
      }
    } finally {
      setUpdateAccountLoading(false);
    }
  };

  const handleSubmit = async () => {
    if (updatePasswordLoading) return;
    try {
      setUpdatePasswordLoading(true);
      await api.post("/api/password-reset/" + data?.id, {
        current_password: password.currentPassword,
        password: password.newPassword,
        password_confirmation: password.confirmPassword,
      });
      setPassword({
        newPassword: "",
        confirmPassword: "",
        currentPassword: "",
      });
    } catch (error) {
      if (axios.isAxiosError(error)) {
        setError(error.response?.data.message);
      }
      setPassword({
        newPassword: "",
        confirmPassword: "",
        currentPassword: "",
      });
    } finally {
      setUpdatePasswordLoading(false);
    }
  };
  return (
    <section>
      <h1 className="text-2xl font-semibold">Profile Settings</h1>
      <div className="mt-10 flex max-w-full flex-col gap-6 pb-10 lg:flex-row lg:items-start lg:justify-between lg:space-x-10 lg:gap-0">
        {/* Account Information */}
        <div className="flex-1 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
          <div className="relative flex flex-col items-start justify-center px-6 py-5 sm:px-9 lg:py-5">
            <h2 className="text-xl font-semibold">Account Information</h2>
            <p className="mt-2 text-sm text-gray-500">Edit your profile</p>
            <div className="relative mt-6 inline-block sm:mt-8 lg:mt-10">
              <div className="relative h-20 w-20 overflow-hidden rounded-full border-2 border-blue-500">
                <img
                  src={getImageUrl(data.image_path)}
                  alt="Profile"
                  loading="lazy"
                  className="h-full w-full cursor-pointer object-cover"
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
                className="absolute bottom-0 right-0 cursor-pointer rounded-full bg-blue-500 p-1.5 text-white hover:bg-blue-600"
              />
            </div>

            <form onSubmit={handleUpdate} className="mt-8 w-full lg:mt-10">
                <label
                  htmlFor="fname"
                  className="block font-semibold text-gray-900"
                >
                  First Name
                </label>
                <input
                  id="fname"
                  name="fname"
                  value={data.fname}
                  onChange={handleChange}
                  className="mt-3 w-full rounded-sm border-b border-gray-400 px-3 py-3 text-sm font-semibold text-gray-800 focus:ring-gray-700"
                />
                <label
                  htmlFor="mi"
                  className="mt-5 block font-semibold text-gray-900"
                >
                  Middle Initial
                </label>
                <input
                  value={data.mi}
                  id="mi"
                  onChange={handleChange}
                  name="mi"
                  className="mt-3 w-full rounded-sm border-b border-gray-400 px-3 py-3 text-sm font-semibold text-gray-800 focus:ring-gray-700"
                />
                <label
                  htmlFor="lname"
                  className="mt-5 block font-semibold text-gray-900"
                >
                  Last Name
                </label>
                <input
                  name="lname"
                  onChange={handleChange}
                  value={data.lname}
                  id="lname"
                  className="mt-3 w-full rounded-sm border-b border-gray-400 px-3 py-3 text-sm font-semibold text-gray-800 focus:ring-gray-700"
                />
                <label
                  htmlFor="suffix"
                  className="mt-5 block font-semibold text-gray-900"
                >
                  Suffix
                </label>
                <input
                  name="suffix"
                  onChange={handleChange}
                  value={data.suffix}
                  id="suffix"
                  className="mt-3 w-full rounded-sm border-b border-gray-400 px-3 py-3 text-sm font-semibold text-gray-800 focus:ring-gray-700"
                />

                <label
                  htmlFor="email"
                  className="mt-5 block font-semibold text-gray-900"
                >
                  Email
                </label>
                <input
                  name="email"
                  value={data.email}
                  onChange={handleChange}
                  id="email"
                  type="email"
                  className="mt-3 w-full rounded-sm border-b border-gray-400 px-3 py-3 text-sm font-semibold text-gray-800 focus:ring-gray-700"
                />
                <Button
                  type="submit"
                  isDisabled={updateAccountLoading}
                  className="mt-5 w-full rounded-sm bg-blue-500 px-2 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed lg:w-full"
                >
                  {updateAccountLoading ? (
                    <AiOutlineLoading3Quarters className="mx-auto animate-spin" />
                  ) : (
                    "Update Account"
                  )}
                </Button>
              </form>
          </div>
        </div>

        {/* Password - Card on mobile/tablet, plain on desktop */}
        <div className="flex-1 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm lg:self-start">
          <div className="px-6 py-6 sm:px-9 sm:py-6 lg:px-5 lg:py-5">
            <h2 className="text-xl font-semibold">Password</h2>
            <p className="mt-2 text-sm text-gray-500">Change your password</p>
            <label
              htmlFor="password"
              className="mt-6 block font-semibold text-gray-900 sm:mt-8 lg:mt-16"
            >
              Current Password
            </label>
            <input
              id="password"
              type="password"
              value={password.currentPassword}
              onChange={(e) =>
                setPassword((prev) => ({
                  ...prev,
                  currentPassword: e.target.value,
                }))
              }
              placeholder="Your current password"
              className="mt-3 w-full rounded-sm border-b border-gray-400 px-3 py-3 text-sm font-semibold text-gray-800 focus:ring-gray-700"
            />
            <label
              htmlFor="newPassword"
              className="mt-5 block font-semibold text-gray-900"
            >
              New Password
            </label>
            <input
              placeholder="Your new password"
              type="password"
              value={password.newPassword}
              onChange={(e) =>
                setPassword((prev) => ({
                  ...prev,
                  newPassword: e.target.value,
                }))
              }
              id="newPassword"
              className="mt-3 w-full rounded-sm border-b border-gray-400 px-3 py-3 text-sm font-semibold text-gray-800 focus:ring-gray-700"
            />
            {password.newPassword !== password.confirmPassword && (
              <p className="text-sm text-red-500">
                Confirm password does not match.
              </p>
            )}
            <label
              htmlFor="confirmPassword"
              className="mt-5 block font-semibold text-gray-900"
            >
              Confirm Password
            </label>
            <input
              placeholder="Confirm new password"
              value={password.confirmPassword}
              onChange={(e) =>
                setPassword((prev) => ({
                  ...prev,
                  confirmPassword: e.target.value,
                }))
              }
              type="password"
              id="confirmPassword"
              className={`mt-3 w-full rounded-sm border-b ${password.newPassword !== password.confirmPassword ? "border-red-500" : "border-green-500"} px-3 py-3 text-sm font-semibold text-gray-800 focus:ring-gray-700`}
            />
            <Button
              type="button"
              isDisabled={updatePasswordLoading}
              onClick={handleSubmit}
              className="mt-5 w-full rounded-sm bg-blue-500 px-2 py-2 text-sm font-semibold text-white hover:bg-blue-600 disabled:cursor-not-allowed lg:mt-5"
            >
              {updatePasswordLoading ? (
                <AiOutlineLoading3Quarters className="mx-auto animate-spin" />
              ) : (
                "Update Password"
              )}
            </Button>
          </div>
        </div>
      </div>
    </section>
  );
};

export default FacultySettings;
